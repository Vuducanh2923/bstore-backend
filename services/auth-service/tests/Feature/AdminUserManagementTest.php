<?php

use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
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
        $table->string('avatar', 500)->nullable();
        $table->string('status', 50)->nullable()->default('active');
    });

    DB::connection('bstore_auth')->table('roles')->insert([
        ['id' => 1, 'name' => User::ROLE_ADMIN, 'description' => 'Quan tri vien'],
        ['id' => 2, 'name' => User::ROLE_STAFF, 'description' => 'Nhan vien'],
        ['id' => 3, 'name' => User::ROLE_CUSTOMER, 'description' => 'Khach hang'],
    ]);
});

function createAuthUserForAdminManagement(string $requestedRole, array $overrides = []): User
{
    static $sequence = 0;
    $sequence++;
    $roleId = DB::connection('bstore_auth')
        ->table('roles')
        ->where('name', $requestedRole)
        ->value('id');

    $user = new User;
    $user->forceFill([
        'full_name' => $overrides['full_name'] ?? "User {$sequence}",
        'email' => $overrides['email'] ?? "user{$sequence}@example.com",
        'password' => $overrides['password'] ?? Hash::make('password'),
        'status' => $overrides['status'] ?? 'active',
        'role_id' => $roleId,
    ]);
    $user->save();

    return $user->load('role');
}

test('admin can list staff only', function () {
    $admin = createAuthUserForAdminManagement(User::ROLE_ADMIN);
    $staff = createAuthUserForAdminManagement(User::ROLE_STAFF);
    createAuthUserForAdminManagement(User::ROLE_CUSTOMER);

    $this->actingAs($admin)
        ->getJson('/api/admin/staff')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $staff->id)
        ->assertJsonPath('data.0.role.name', User::ROLE_STAFF);
});

test('admin and staff bearer tokens can list customers', function () {
    $admin = createAuthUserForAdminManagement(User::ROLE_ADMIN);
    $staff = createAuthUserForAdminManagement(User::ROLE_STAFF);
    $customer = createAuthUserForAdminManagement(User::ROLE_CUSTOMER);
    $tokens = app(AuthTokenService::class);

    $this->withToken($tokens->generate($admin))
        ->getJson('/api/admin/customers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $customer->id);

    $this->withToken($tokens->generate($staff))
        ->getJson('/api/admin/customers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $customer->id);
});

test('admin login token can list customers', function () {
    $admin = createAuthUserForAdminManagement(User::ROLE_ADMIN, [
        'email' => 'admin@example.com',
        'password' => Hash::make('secret123'),
    ]);
    $customer = createAuthUserForAdminManagement(User::ROLE_CUSTOMER);

    $token = $this->postJson('/api/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id)
        ->json('data.token');

    expect($token)->toBeString()->not->toBeEmpty();

    $this->withToken($token)
        ->getJson('/api/admin/customers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $customer->id);
});

test('customer bearer token cannot list customers', function () {
    $customer = createAuthUserForAdminManagement(User::ROLE_CUSTOMER);

    $this->withToken(app(AuthTokenService::class)->generate($customer))
        ->getJson('/api/admin/customers')
        ->assertForbidden();
});

test('admin can create staff with staff role by default', function () {
    $admin = createAuthUserForAdminManagement(User::ROLE_ADMIN);

    $this->actingAs($admin)
        ->postJson('/api/admin/staff', [
            'full_name' => 'Staff One',
            'email' => 'staff.one@example.com',
            'password' => 'secret123',
            'role' => User::ROLE_CUSTOMER,
        ])
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->postJson('/api/admin/staff', [
            'full_name' => 'Staff One',
            'email' => 'staff.one@example.com',
            'password' => 'secret123',
        ])
        ->assertCreated()
        ->assertJsonPath('data.role.name', User::ROLE_STAFF)
        ->assertJsonMissingPath('data.password');

    $staff = User::with('role')->where('email', 'staff.one@example.com')->firstOrFail();

    expect($staff->role?->name)->toBe(User::ROLE_STAFF)
        ->and(Hash::check('secret123', $staff->password))->toBeTrue();
});

test('admin cannot change a user role to admin', function () {
    $admin = createAuthUserForAdminManagement(User::ROLE_ADMIN);
    $customer = createAuthUserForAdminManagement(User::ROLE_CUSTOMER);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$customer->id}/role", [
            'role' => User::ROLE_ADMIN,
        ])
        ->assertUnprocessable();

    expect($customer->fresh('role')->role?->name)->toBe(User::ROLE_CUSTOMER);
});

test('admin can change a user role to staff or customer', function () {
    $admin = createAuthUserForAdminManagement(User::ROLE_ADMIN);
    $customer = createAuthUserForAdminManagement(User::ROLE_CUSTOMER);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$customer->id}/role", [
            'role' => User::ROLE_STAFF,
        ])
        ->assertOk()
        ->assertJsonPath('data.role.name', User::ROLE_STAFF);

    expect($customer->fresh('role')->role?->name)->toBe(User::ROLE_STAFF);
});

test('admin cannot downgrade self', function () {
    $admin = createAuthUserForAdminManagement(User::ROLE_ADMIN);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$admin->id}/role", [
            'role' => User::ROLE_STAFF,
        ])
        ->assertForbidden();

    expect($admin->fresh('role')->role?->name)->toBe(User::ROLE_ADMIN);
});

test('admin can delete only the requested user role type', function () {
    $admin = createAuthUserForAdminManagement(User::ROLE_ADMIN);
    $staff = createAuthUserForAdminManagement(User::ROLE_STAFF);
    $customer = createAuthUserForAdminManagement(User::ROLE_CUSTOMER);

    $this->actingAs($admin)
        ->deleteJson("/api/admin/customers/{$staff->id}")
        ->assertNotFound();

    expect(User::find($staff->id))->not->toBeNull();

    $this->actingAs($admin)
        ->deleteJson("/api/admin/customers/{$customer->id}")
        ->assertOk();

    expect(User::find($customer->id))->toBeNull();
});

test('staff can list customers but cannot list staff', function () {
    $staff = createAuthUserForAdminManagement(User::ROLE_STAFF);
    $customer = createAuthUserForAdminManagement(User::ROLE_CUSTOMER);

    $this->getJson('/api/admin/staff')->assertUnauthorized();
    $this->getJson('/api/admin/customers')->assertUnauthorized();

    $this->actingAs($staff)
        ->getJson('/api/admin/customers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $customer->id);

    $this->actingAs($staff)
        ->getJson('/api/admin/staff')
        ->assertForbidden();
});
