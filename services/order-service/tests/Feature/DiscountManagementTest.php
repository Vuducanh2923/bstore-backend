<?php

use App\Models\Discount;
use App\Services\AuthTokenService;
use App\Services\OrderDiscountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config([
        'database.connections.bstore_order' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);
    DB::purge('bstore_order');

    Schema::connection('bstore_order')->create('discounts', function (Blueprint $table) {
        $table->id();
        $table->string('code')->unique();
        $table->string('name');
        $table->text('description')->nullable();
        $table->string('type');
        $table->decimal('value', 15, 2);
        $table->decimal('max_discount_amount', 15, 2)->nullable();
        $table->decimal('min_order_amount', 15, 2)->default(0);
        $table->unsignedInteger('usage_limit')->nullable();
        $table->unsignedInteger('usage_limit_per_customer')->nullable();
        $table->unsignedInteger('used_count')->default(0);
        $table->timestamp('start_date')->nullable();
        $table->timestamp('end_date')->nullable();
        $table->string('status')->default('active');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::connection('bstore_order')->create('orders', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
    });
    Schema::connection('bstore_order')->create('order_discounts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->unsignedBigInteger('discount_id');
        $table->string('discount_code');
        $table->decimal('discount_amount', 15, 2);
    });
});

// Thực hiện mã giảm giá token.
function discountToken(string $role = 'ADMIN', int $id = 90): string
{
    return app(AuthTokenService::class)->generate($id, $role, "user{$id}@example.com");
}

// Thực hiện mã giảm giá dữ liệu gửi.
function discountPayload(array $overrides = []): array
{
    return array_merge([
        'code' => ' bstore10 ',
        'name' => 'Giam gia thang 7',
        'description' => 'Giảm cho đơn hàng hợp lệ',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'max_discount_amount' => 500000,
        'min_order_amount' => 2000000,
        'usage_limit' => 100,
        'usage_limit_per_customer' => 1,
        'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addMonth()->format('Y-m-d H:i:s'),
        'status' => 'active',
    ], $overrides);
}

// Thực hiện insert mã giảm giá.
function insertDiscount(array $overrides = []): int
{
    return DB::connection('bstore_order')->table('discounts')->insertGetId(array_merge([
        'code' => 'TEST10',
        'name' => 'Test discount',
        'type' => 'percentage',
        'value' => 10,
        'min_order_amount' => 0,
        'used_count' => 0,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDay(),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

test('admin creates a valid percentage discount', function () {
    $this->withToken(discountToken())->postJson('/api/admin/discount-codes', discountPayload())
        ->assertCreated()
        ->assertJsonPath('data.code', 'BSTORE10')
        ->assertJsonPath('data.discount_type', 'percentage')
        ->assertJsonPath('data.used_count', 0)
        ->assertJsonPath('data.created_by', 90);
});

test('admin creates a fixed amount discount', function () {
    $this->withToken(discountToken())->postJson('/api/admin/discount-codes', discountPayload([
        'code' => 'FIXED100',
        'discount_type' => 'fixed_amount',
        'discount_value' => 100000,
        'max_discount_amount' => null,
    ]))->assertCreated()
        ->assertJsonPath('data.discount_type', 'fixed_amount')
        ->assertJsonPath('data.discount_value', '100000.00');
});

test('duplicate code is rejected case insensitively', function () {
    insertDiscount(['code' => 'BSTORE10']);

    $this->withToken(discountToken())->postJson('/api/admin/discount-codes', discountPayload([
        'code' => 'bstore10',
    ]))->assertConflict()->assertJsonPath('message', 'Mã giảm giá đã tồn tại');
});

test('percentage greater than one hundred is invalid', function () {
    $this->withToken(discountToken())->postJson('/api/admin/discount-codes', discountPayload([
        'discount_value' => 101,
    ]))->assertUnprocessable();
});

test('start date must precede end date', function () {
    $this->withToken(discountToken())->postJson('/api/admin/discount-codes', discountPayload([
        'starts_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
    ]))->assertUnprocessable();
});

test('unused discount is soft deleted', function () {
    $id = insertDiscount();

    $this->withToken(discountToken())->deleteJson("/api/admin/discount-codes/{$id}")
        ->assertOk()
        ->assertJsonPath('message', 'Xóa mã giảm giá thành công');

    expect(Discount::find($id))->toBeNull()
        ->and(Discount::withTrashed()->find($id))->not->toBeNull();
});

test('used discount is deactivated instead of deleted', function () {
    $id = insertDiscount(['used_count' => 1]);
    $orderId = DB::connection('bstore_order')->table('orders')->insertGetId(['user_id' => 10]);
    DB::connection('bstore_order')->table('order_discounts')->insert([
        'order_id' => $orderId,
        'discount_id' => $id,
        'discount_code' => 'TEST10',
        'discount_amount' => 10000,
    ]);

    $this->withToken(discountToken())->deleteJson("/api/admin/discount-codes/{$id}")
        ->assertOk()
        ->assertJsonPath('message', 'Mã giảm giá đã được ngừng áp dụng')
        ->assertJsonPath('data.status', 'inactive');

    expect(Discount::find($id))->not->toBeNull();
});

test('customer cannot access admin discount APIs', function () {
    $this->withToken(discountToken('CUSTOMER', 10))
        ->getJson('/api/admin/discount-codes')
        ->assertForbidden();
});

test('customer previews a discount without consuming usage', function () {
    $id = insertDiscount([
        'code' => 'PREVIEW10',
        'value' => 10,
        'min_order_amount' => 100000,
    ]);

    $this->withToken(discountToken('CUSTOMER', 10))
        ->postJson('/api/customer/discount-codes/preview', [
            'discount_code' => 'preview10',
            'subtotal' => 1000000,
        ])
        ->assertOk()
        ->assertJsonPath('data.discount_code', 'PREVIEW10')
        ->assertJsonPath('data.discount_amount', 100000)
        ->assertJsonPath('data.final_amount', 900000);

    expect((int) DB::connection('bstore_order')->table('discounts')->where('id', $id)->value('used_count'))
        ->toBe(0);
});

test('expired discount cannot be applied', function () {
    $id = insertDiscount(['end_date' => now()->subMinute()]);

    expect(fn () => app(OrderDiscountService::class)->resolve([
        ['discount_id' => $id],
    ], 1000000, 10))->toThrow(ValidationException::class, 'đã hết hạn');
});

test('discount over usage limit cannot be applied', function () {
    $id = insertDiscount(['usage_limit' => 1, 'used_count' => 1]);

    expect(fn () => app(OrderDiscountService::class)->resolve([
        ['discount_id' => $id],
    ], 1000000, 10))->toThrow(ValidationException::class, 'đã hết lượt sử dụng');
});

test('percentage discount respects maximum amount and per customer usage', function () {
    $id = insertDiscount([
        'value' => 20,
        'max_discount_amount' => 50000,
        'usage_limit_per_customer' => 1,
    ]);
    $resolved = DB::connection('bstore_order')->transaction(
        fn () => app(OrderDiscountService::class)->resolve([['discount_id' => $id]], 1000000, 10),
    );
    expect($resolved[0]['discount_amount'])->toBe(50000.0);

    $orderId = DB::connection('bstore_order')->table('orders')->insertGetId(['user_id' => 10]);
    DB::connection('bstore_order')->table('order_discounts')->insert([
        'order_id' => $orderId,
        'discount_id' => $id,
        'discount_code' => 'TEST10',
        'discount_amount' => 50000,
    ]);

    expect(fn () => DB::connection('bstore_order')->transaction(
        fn () => app(OrderDiscountService::class)->resolve([['discount_id' => $id]], 1000000, 10),
    ))->toThrow(ValidationException::class, 'hết lượt');
});
