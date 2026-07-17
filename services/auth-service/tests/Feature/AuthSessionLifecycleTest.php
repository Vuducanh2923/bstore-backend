<?php

use App\Models\AuthSession;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'auth.internal_service_token' => 'test-internal-service-token-at-least-32-bytes',
        'database.connections.bstore_auth' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    DB::purge('bstore_auth');

    Schema::connection('bstore_auth')->create('roles', function (Blueprint $table) {
        $table->id();
        $table->string('name', 100)->unique();
        $table->text('description')->nullable();
    });

    Schema::connection('bstore_auth')->create('users', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('role_id')->nullable();
        $table->string('full_name', 191);
        $table->string('email', 191)->unique();
        $table->string('password');
        $table->string('phone', 30)->nullable();
        $table->string('status', 50)->default('active');
        $table->dateTime('last_login_at')->nullable();
    });

    Schema::connection('bstore_auth')->create('auth_sessions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->unsignedBigInteger('user_id')->index();
        $table->uuid('access_jti')->unique();
        $table->char('refresh_token_hash', 64)->unique();
        $table->dateTime('refresh_expires_at')->index();
        $table->dateTime('last_used_at')->nullable();
        $table->dateTime('revoked_at')->nullable()->index();
        $table->timestamps();
    });

    DB::connection('bstore_auth')->table('roles')->insert([
        ['id' => 1, 'name' => User::ROLE_ADMIN, 'description' => 'Quan tri vien'],
        ['id' => 2, 'name' => User::ROLE_STAFF, 'description' => 'Nhan vien'],
        ['id' => 3, 'name' => User::ROLE_CUSTOMER, 'description' => 'Khach hang'],
    ]);
});

function createSessionTestUser(array $overrides = []): User
{
    static $sequence = 0;
    $sequence++;

    $user = new User;
    $user->forceFill([
        'role_id' => $overrides['role_id'] ?? 1,
        'full_name' => $overrides['full_name'] ?? "Session User {$sequence}",
        'email' => $overrides['email'] ?? "session{$sequence}@example.com",
        'phone' => $overrides['phone'] ?? '0900000000',
        'password' => $overrides['password'] ?? Hash::make('secret123'),
        'status' => $overrides['status'] ?? 'active',
    ]);
    $user->save();

    return $user->load('role');
}

function loginSessionTestUser($test, User $user): array
{
    $data = $test->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonStructure(['data' => ['token', 'refresh_token', 'expires_in']])
        ->json('data');

    expect($data['expires_in'])->toBe(900);

    return $data;
}

test('refresh token is hashed and rotated and invalidates the previous token pair', function () {
    $admin = createSessionTestUser();
    $first = loginSessionTestUser($this, $admin);
    $session = AuthSession::query()->sole();

    expect($session->refresh_token_hash)
        ->toBe(hash('sha256', $first['refresh_token']))
        ->not->toBe($first['refresh_token']);

    $second = $this->postJson('/api/auth/refresh', [
        'refresh_token' => $first['refresh_token'],
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'refresh_token', 'expires_in']])
        ->json('data');

    expect($second['token'])->not->toBe($first['token'])
        ->and($second['refresh_token'])->not->toBe($first['refresh_token'])
        ->and(AuthSession::query()->count())->toBe(1)
        ->and(AuthSession::query()->sole()->refresh_token_hash)
        ->toBe(hash('sha256', $second['refresh_token']));

    $this->postJson('/api/auth/refresh', [
        'refresh_token' => $first['refresh_token'],
    ])->assertUnauthorized();

    $this->withToken($first['token'])->getJson('/api/users')->assertUnauthorized();
    $this->withToken($second['token'])->getJson('/api/users')->assertOk();
});

test('logout revokes access and refresh tokens for the session', function () {
    $admin = createSessionTestUser();
    $credentials = loginSessionTestUser($this, $admin);

    $this->withToken($credentials['token'])
        ->postJson('/api/auth/logout')
        ->assertOk();

    expect(AuthSession::query()->sole()->revoked_at)->not->toBeNull();

    $this->withToken($credentials['token'])->getJson('/api/users')->assertUnauthorized();

    $this->postJson('/api/auth/refresh', [
        'refresh_token' => $credentials['refresh_token'],
    ])->assertUnauthorized();

    $this->withHeader('X-Internal-Service-Token', 'test-internal-service-token-at-least-32-bytes')
        ->postJson('/api/internal/auth/introspect', ['token' => $credentials['token']])
        ->assertOk()
        ->assertJsonPath('data.active', false);
});

test('authenticated users can restore their session through auth me', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();

    foreach ([1, 2, 3] as $roleId) {
        $user = createSessionTestUser(['role_id' => $roleId]);
        $credentials = loginSessionTestUser($this, $user);

        $this->withToken($credentials['token'])
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }
});

test('inactive users cannot login refresh introspect or use protected routes', function () {
    $blocked = createSessionTestUser([
        'email' => 'blocked@example.com',
        'status' => 'blocked',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $blocked->email,
        'password' => 'secret123',
    ])->assertForbidden();

    $admin = createSessionTestUser(['email' => 'active-then-blocked@example.com']);
    $credentials = loginSessionTestUser($this, $admin);
    $admin->forceFill(['status' => 'blocked'])->save();

    $this->withHeader('X-Internal-Service-Token', 'test-internal-service-token-at-least-32-bytes')
        ->postJson('/api/internal/auth/introspect', ['token' => $credentials['token']])
        ->assertOk()
        ->assertJsonPath('data.active', false);

    $this->postJson('/api/auth/refresh', [
        'refresh_token' => $credentials['refresh_token'],
    ])->assertForbidden();

    expect(AuthSession::query()->sole()->revoked_at)->not->toBeNull();

    $this->withToken($credentials['token'])->getJson('/api/users')->assertUnauthorized();
});

test('generic resources are denied and explicit user and role routes require admin', function () {
    $this->getJson('/api/users')->assertUnauthorized();
    $this->getJson('/api/roles')->assertUnauthorized();
    $this->postJson('/api/users', [])->assertStatus(405);
    $this->postJson('/api/roles', [])->assertStatus(405);
    $this->getJson('/api/not-a-resource')->assertNotFound();
    $this->deleteJson('/api/users/1')->assertStatus(405);

    $admin = createSessionTestUser();
    $credentials = loginSessionTestUser($this, $admin);

    $this->withToken($credentials['token'])->getJson('/api/users')->assertOk();
    $this->withToken($credentials['token'])->getJson('/api/roles')->assertOk();
});

test('internal introspection and minimal user lookup require the service token', function () {
    $admin = createSessionTestUser();
    $credentials = loginSessionTestUser($this, $admin);

    $this->postJson('/api/internal/auth/introspect', [
        'token' => $credentials['token'],
    ])->assertUnauthorized();

    $this->getJson("/api/internal/users/{$admin->id}")->assertUnauthorized();

    $this->withHeader('X-Internal-Service-Token', 'test-internal-service-token-at-least-32-bytes')
        ->postJson('/api/internal/auth/introspect', ['token' => $credentials['token']])
        ->assertOk()
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.token_type', 'access')
        ->assertJsonPath('data.sub', $admin->id)
        ->assertJsonPath('data.role', User::ROLE_ADMIN);

    $lastCharacter = substr($credentials['token'], -1);
    $tamperedToken = substr($credentials['token'], 0, -1).($lastCharacter === 'a' ? 'b' : 'a');

    $this->withHeader('X-Internal-Service-Token', 'test-internal-service-token-at-least-32-bytes')
        ->postJson('/api/internal/auth/introspect', ['token' => $tamperedToken])
        ->assertOk()
        ->assertJsonPath('data.active', false);

    $this->withHeader('X-Internal-Service-Token', 'test-internal-service-token-at-least-32-bytes')
        ->getJson("/api/internal/users/{$admin->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id)
        ->assertJsonPath('data.role.name', User::ROLE_ADMIN)
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.address');
});

test('token issuance fails closed when auth token key is missing', function () {
    $admin = createSessionTestUser();
    config(['auth.token_key' => null]);

    expect(fn () => app(AuthTokenService::class)->issue($admin))
        ->toThrow(RuntimeException::class, 'AUTH_TOKEN_KEY is required.');

    expect(AuthSession::query()->count())->toBe(0);
});
