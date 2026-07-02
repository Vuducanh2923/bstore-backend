<?php

use App\Services\AuthTokenService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'database.default' => 'bstore_order',
        'database.connections.bstore_order' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
        'database.connections.bstore_catalog' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    DB::purge('bstore_order');
    DB::purge('bstore_catalog');

    foreach (['order_items', 'orders'] as $table) {
        Schema::connection('bstore_order')->dropIfExists($table);
    }

    foreach (['product_images', 'product_variants'] as $table) {
        Schema::connection('bstore_catalog')->dropIfExists($table);
    }

    Schema::connection('bstore_order')->create('orders', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->index();
        $table->string('order_code', 191)->nullable()->unique();
        $table->string('receiver_name');
        $table->string('receiver_phone', 20);
        $table->string('receiver_email', 191)->nullable();
        $table->text('shipping_address');
        $table->string('shipping_method', 50);
        $table->string('payment_method', 50)->nullable();
        $table->decimal('total_amount', 15, 2)->default(0);
        $table->decimal('discount_amount', 15, 2)->default(0);
        $table->decimal('shipping_fee', 15, 2)->default(0);
        $table->decimal('final_amount', 15, 2)->default(0);
        $table->string('status', 20)->nullable()->default('pending');
        $table->string('payment_status', 20)->nullable()->default('unpaid');
        $table->text('cancel_reason')->nullable();
        $table->text('note')->nullable();
        $table->dateTime('created_at')->nullable();
        $table->dateTime('updated_at')->nullable();
    });

    Schema::connection('bstore_order')->create('order_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id')->index();
        $table->unsignedBigInteger('product_id')->nullable()->index();
        $table->unsignedBigInteger('product_variant_id')->index();
        $table->string('product_name');
        $table->string('product_image', 500)->nullable();
        $table->string('color', 50)->nullable();
        $table->string('ram', 50)->nullable();
        $table->string('storage', 50)->nullable();
        $table->decimal('price', 15, 2)->default(0);
        $table->unsignedInteger('quantity')->default(1);
        $table->decimal('subtotal', 15, 2)->default(0);
    });

    Schema::connection('bstore_catalog')->create('product_variants', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_id')->index();
    });

    Schema::connection('bstore_catalog')->create('product_images', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_id')->index();
        $table->unsignedBigInteger('product_variant_id')->nullable()->index();
        $table->string('image_url', 500);
        $table->boolean('is_thumbnail')->default(false);
    });
});

function adminOrderTokenForTest(string $role = 'ADMIN'): string
{
    return app(AuthTokenService::class)->generate(1, $role, 'admin@example.com');
}

function insertAdminOrderForTest(array $overrides = []): int
{
    $id = DB::connection('bstore_order')->table('orders')->insertGetId([
        'user_id' => $overrides['user_id'] ?? 10,
        'order_code' => $overrides['order_code'] ?? 'ORD-ADMIN',
        'receiver_name' => $overrides['receiver_name'] ?? 'Nguyen Van A',
        'receiver_phone' => $overrides['receiver_phone'] ?? '0900000001',
        'receiver_email' => $overrides['receiver_email'] ?? 'a@example.com',
        'shipping_address' => $overrides['shipping_address'] ?? '12 Nguyen Hue',
        'shipping_method' => $overrides['shipping_method'] ?? 'standard',
        'payment_method' => $overrides['payment_method'] ?? 'cod',
        'total_amount' => $overrides['total_amount'] ?? 120000,
        'discount_amount' => $overrides['discount_amount'] ?? 10000,
        'shipping_fee' => $overrides['shipping_fee'] ?? 15000,
        'final_amount' => $overrides['final_amount'] ?? 125000,
        'status' => $overrides['status'] ?? 'shipping',
        'payment_status' => $overrides['payment_status'] ?? 'paid',
        'note' => $overrides['note'] ?? 'Giao gio hanh chinh',
        'created_at' => $overrides['created_at'] ?? now(),
        'updated_at' => $overrides['updated_at'] ?? now(),
    ]);

    DB::connection('bstore_order')->table('order_items')->insert([
        'order_id' => $id,
        'product_id' => $overrides['product_id'] ?? 1001,
        'product_variant_id' => $overrides['product_variant_id'] ?? 501,
        'product_name' => $overrides['product_name'] ?? 'Phone A',
        'product_image' => $overrides['product_image'] ?? 'https://cdn.test/phone-a.jpg',
        'price' => $overrides['price'] ?? 60000,
        'quantity' => $overrides['quantity'] ?? 2,
        'subtotal' => $overrides['subtotal'] ?? 120000,
    ]);

    return $id;
}

test('admin can list orders through protected admin endpoint', function () {
    $olderOrderId = insertAdminOrderForTest([
        'order_code' => 'ORD-OLDER',
        'created_at' => now()->subDay(),
    ]);
    $newerOrderId = insertAdminOrderForTest([
        'order_code' => 'ORD-NEWER',
        'created_at' => now(),
    ]);

    $this->withToken(adminOrderTokenForTest())
        ->getJson('/api/admin/orders')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.order_id', $newerOrderId)
        ->assertJsonPath('data.0.customer_name', 'Nguyen Van A')
        ->assertJsonPath('data.0.customer_email', 'a@example.com')
        ->assertJsonPath('data.0.subtotal', '120000.00')
        ->assertJsonPath('data.0.total_amount', '125000.00')
        ->assertJsonPath('data.1.order_id', $olderOrderId)
        ->assertJsonMissingPath('data.0.items');
});

test('admin can view order detail with saved customer and item snapshots', function () {
    $orderId = insertAdminOrderForTest([
        'order_code' => 'ORD-DETAIL',
        'receiver_name' => 'Tran Thi B',
        'receiver_email' => 'b@example.com',
        'receiver_phone' => '0900000002',
        'shipping_address' => '99 Le Loi',
        'payment_method' => 'bank_transfer',
    ]);

    $this->withToken(adminOrderTokenForTest())
        ->getJson("/api/admin/orders/{$orderId}")
        ->assertOk()
        ->assertJsonPath('data.order_id', $orderId)
        ->assertJsonPath('data.order_code', 'ORD-DETAIL')
        ->assertJsonPath('data.user_id', 10)
        ->assertJsonPath('data.customer_name', 'Tran Thi B')
        ->assertJsonPath('data.customer_email', 'b@example.com')
        ->assertJsonPath('data.customer_phone', '0900000002')
        ->assertJsonPath('data.shipping_address', '99 Le Loi')
        ->assertJsonPath('data.status', 'shipping')
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payment_method', 'bank_transfer')
        ->assertJsonPath('data.subtotal', '120000.00')
        ->assertJsonPath('data.discount_amount', '10000.00')
        ->assertJsonPath('data.shipping_fee', '15000.00')
        ->assertJsonPath('data.total_amount', '125000.00')
        ->assertJsonPath('data.items.0.product_id', 1001)
        ->assertJsonPath('data.items.0.product_name', 'Phone A')
        ->assertJsonPath('data.items.0.product_image', 'https://cdn.test/phone-a.jpg')
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.items.0.unit_price', '60000.00')
        ->assertJsonPath('data.items.0.total_price', '120000.00')
        ->assertJsonStructure([
            'data' => [
                'created_at',
                'updated_at',
            ],
        ]);
});

test('admin order endpoints require admin token', function () {
    $orderId = insertAdminOrderForTest();

    $this->getJson('/api/admin/orders')->assertUnauthorized();

    $this->withToken(adminOrderTokenForTest('CUSTOMER'))
        ->getJson("/api/admin/orders/{$orderId}")
        ->assertForbidden();
});

test('admin can update order status', function () {
    Mail::fake();

    $orderId = insertAdminOrderForTest([
        'status' => 'pending',
    ]);

    $this->withToken(adminOrderTokenForTest())
        ->patchJson("/api/admin/orders/{$orderId}/status", [
            'status' => 'confirmed',
        ])
        ->assertOk()
        ->assertJsonPath('data.order_id', $orderId)
        ->assertJsonPath('data.status', 'confirmed');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'status' => 'confirmed',
    ], 'bstore_order');
});

test('staff can access and update admin orders', function () {
    Mail::fake();

    $orderId = insertAdminOrderForTest([
        'order_code' => 'ORD-STAFF',
        'status' => 'pending',
    ]);
    $staffToken = adminOrderTokenForTest('STAFF');

    $this->withToken($staffToken)
        ->getJson('/api/admin/orders')
        ->assertOk()
        ->assertJsonPath('data.0.order_code', 'ORD-STAFF');

    $this->withToken($staffToken)
        ->getJson("/api/admin/orders/{$orderId}")
        ->assertOk()
        ->assertJsonPath('data.order_id', $orderId);

    $this->withToken($staffToken)
        ->patchJson("/api/admin/orders/{$orderId}/status", [
            'status' => 'confirmed',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');
});
