<?php

use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'database.connections.bstore_auth' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
        'services.order.url' => 'http://order.test',
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
        $table->string('address', 500)->nullable();
        $table->string('province', 100)->nullable();
        $table->string('district', 100)->nullable();
        $table->string('ward', 100)->nullable();
        $table->text('default_shipping_address')->nullable();
        $table->string('gender', 20)->nullable();
        $table->date('date_of_birth')->nullable();
        $table->string('avatar', 500)->nullable();
        $table->string('status', 50)->nullable()->default('active');
        $table->dateTime('last_login_at')->nullable();
        $table->dateTime('created_at')->nullable();
    });

    Schema::connection('bstore_auth')->create('user_addresses', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->index();
        $table->string('receiver_name', 100);
        $table->string('receiver_phone', 30);
        $table->string('receiver_email', 191)->nullable();
        $table->string('address', 500);
        $table->string('province', 100)->nullable();
        $table->string('district', 100)->nullable();
        $table->string('ward', 100)->nullable();
        $table->boolean('is_default')->default(false);
        $table->timestamps();
    });

    DB::connection('bstore_auth')->table('roles')->insert([
        ['id' => 1, 'name' => User::ROLE_ADMIN, 'description' => 'Quan tri vien'],
        ['id' => 2, 'name' => User::ROLE_STAFF, 'description' => 'Nhan vien'],
        ['id' => 3, 'name' => User::ROLE_CUSTOMER, 'description' => 'Khach hang'],
    ]);
});

function createAuthUserForCustomerAccount(string $role, array $overrides = []): User
{
    static $sequence = 0;
    $sequence++;

    $roleId = DB::connection('bstore_auth')
        ->table('roles')
        ->where('name', $role)
        ->value('id');

    $user = new User;
    $user->forceFill([
        'role_id' => $roleId,
        'full_name' => $overrides['full_name'] ?? "Account User {$sequence}",
        'email' => $overrides['email'] ?? "account{$sequence}@example.com",
        'phone' => $overrides['phone'] ?? null,
        'password' => $overrides['password'] ?? Hash::make('secret123'),
        'status' => $overrides['status'] ?? 'active',
    ]);
    $user->save();

    return $user->load('role');
}

test('customer can update profile change password and manage default address', function () {
    $customer = createAuthUserForCustomerAccount(User::ROLE_CUSTOMER, [
        'email' => 'customer@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $token = app(AuthTokenService::class)->generate($customer);

    $this->withToken($token)
        ->putJson('/api/profile', [
            'full_name' => 'Nguyen Van A',
            'email' => 'new.customer@example.com',
            'phone' => '0900000001',
            'address' => '12 Nguyen Hue',
            'province' => 'TP HCM',
            'district' => 'Quan 1',
            'ward' => 'Ben Nghe',
            'gender' => 'male',
            'date_of_birth' => '1998-01-02',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.full_name', 'Nguyen Van A');

    $addressId = $this->withToken($token)
        ->postJson('/api/profile/addresses', [
            'receiver_name' => 'Nguyen Van A',
            'receiver_phone' => '0900000001',
            'address' => '12 Nguyen Hue',
            'province' => 'TP HCM',
            'district' => 'Quan 1',
            'ward' => 'Ben Nghe',
            'is_default' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_default', true)
        ->json('data.id');

    expect($customer->fresh()->default_shipping_address)->toBe('12 Nguyen Hue, Ben Nghe, Quan 1, TP HCM');

    $this->withToken($token)
        ->getJson('/api/profile/addresses')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withToken($token)
        ->patchJson("/api/profile/addresses/{$addressId}/default")
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    $this->withToken($token)
        ->putJson('/api/profile/change-password', [
            'current_password' => 'secret123',
            'new_password' => 'secret456',
            'new_password_confirmation' => 'secret456',
        ])
        ->assertOk();

    expect(Hash::check('secret456', $customer->fresh()->password))->toBeTrue();
});

test('staff can view customer detail with order history but cannot change status', function () {
    $staff = createAuthUserForCustomerAccount(User::ROLE_STAFF);
    $customer = createAuthUserForCustomerAccount(User::ROLE_CUSTOMER);

    $customer->addresses()->create([
        'receiver_name' => 'Customer',
        'receiver_phone' => '0900000002',
        'address' => '34 Le Loi',
        'is_default' => true,
    ]);

    Http::fake([
        "http://order.test/api/internal/customers/{$customer->id}/orders" => Http::response([
            'success' => true,
            'message' => 'ok',
            'data' => [
                ['id' => 99, 'order_code' => 'ORD99'],
            ],
        ]),
    ]);

    $token = app(AuthTokenService::class)->generate($staff);

    $this->withToken($token)
        ->getJson("/api/admin/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('data.customer.id', $customer->id)
        ->assertJsonPath('data.orders.0.order_code', 'ORD99');

    $this->withToken($token)
        ->patchJson("/api/admin/customers/{$customer->id}/status", [
            'status' => 'blocked',
        ])
        ->assertForbidden();
});
