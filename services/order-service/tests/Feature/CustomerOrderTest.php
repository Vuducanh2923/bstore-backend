<?php

use App\Services\AuthTokenService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    Schema::connection('bstore_order')->create('orders', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->index();
        $table->string('order_code', 191)->nullable()->unique();
        $table->string('receiver_name');
        $table->string('receiver_phone', 20);
        $table->string('receiver_email', 191)->nullable();
        $table->text('shipping_address');
        $table->string('shipping_method', 50);
        $table->decimal('total_amount', 15, 2)->default(0);
        $table->decimal('discount_amount', 15, 2)->default(0);
        $table->decimal('final_amount', 15, 2)->default(0);
        $table->string('status', 20)->nullable()->default('pending');
        $table->string('payment_status', 20)->nullable()->default('unpaid');
        $table->text('cancel_reason')->nullable();
        $table->text('note')->nullable();
        $table->dateTime('created_at')->nullable();
    });

    Schema::connection('bstore_order')->create('order_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id')->index();
        $table->unsignedBigInteger('product_variant_id')->index();
        $table->string('product_name');
        $table->string('color', 50)->nullable();
        $table->string('ram', 50)->nullable();
        $table->string('storage', 50)->nullable();
        $table->decimal('price', 15, 2)->default(0);
        $table->unsignedInteger('quantity')->default(1);
        $table->decimal('subtotal', 15, 2)->default(0);
    });
});

function insertCustomerOrderForTest(array $overrides = []): int
{
    $id = DB::connection('bstore_order')->table('orders')->insertGetId([
        'user_id' => $overrides['user_id'] ?? 10,
        'order_code' => $overrides['order_code'] ?? 'ORD-TEST',
        'receiver_name' => 'Nguyen Van A',
        'receiver_phone' => '0900000001',
        'receiver_email' => 'a@example.com',
        'shipping_address' => '12 Nguyen Hue',
        'shipping_method' => 'standard',
        'total_amount' => 100000,
        'discount_amount' => 10000,
        'final_amount' => 90000,
        'status' => $overrides['status'] ?? 'shipping',
        'payment_status' => $overrides['payment_status'] ?? 'paid',
        'note' => 'Giao gio hanh chinh',
        'created_at' => $overrides['created_at'] ?? now(),
    ]);

    DB::connection('bstore_order')->table('order_items')->insert([
        'order_id' => $id,
        'product_variant_id' => 501,
        'product_name' => 'Phone A',
        'price' => 100000,
        'quantity' => 1,
        'subtotal' => 100000,
    ]);

    return $id;
}

test('customer orders are filtered by token user and include vietnamese status labels', function () {
    $olderOrderId = insertCustomerOrderForTest([
        'user_id' => 10,
        'order_code' => 'ORD-OLD',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'created_at' => now()->subDay(),
    ]);
    $newerOrderId = insertCustomerOrderForTest([
        'user_id' => 10,
        'order_code' => 'ORD-NEW',
        'status' => 'shipping',
        'payment_status' => 'paid',
        'created_at' => now(),
    ]);
    insertCustomerOrderForTest([
        'user_id' => 11,
        'order_code' => 'ORD-OTHER',
    ]);

    $token = app(AuthTokenService::class)->generate(10, 'CUSTOMER', 'customer@example.com');

    $this->withToken($token)
        ->getJson('/api/customer/orders')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newerOrderId)
        ->assertJsonPath('data.0.status_label', 'Đang giao hàng')
        ->assertJsonPath('data.0.payment_status_label', 'Đã thanh toán')
        ->assertJsonPath('data.1.id', $olderOrderId);
});

test('customer can view only own order detail and internal endpoint returns order items', function () {
    $ownOrderId = insertCustomerOrderForTest([
        'user_id' => 10,
        'order_code' => 'ORD-OWN',
    ]);
    $otherOrderId = insertCustomerOrderForTest([
        'user_id' => 11,
        'order_code' => 'ORD-OTHER',
    ]);

    $token = app(AuthTokenService::class)->generate(10, 'CUSTOMER', 'customer@example.com');

    $this->withToken($token)
        ->getJson("/api/customer/orders/{$ownOrderId}")
        ->assertOk()
        ->assertJsonPath('data.id', $ownOrderId)
        ->assertJsonCount(1, 'data.items');

    $this->withToken($token)
        ->getJson("/api/customer/orders/{$otherOrderId}")
        ->assertNotFound();

    $this->getJson('/api/internal/customers/10/orders')
        ->assertOk()
        ->assertJsonPath('data.0.order_code', 'ORD-OWN')
        ->assertJsonCount(1, 'data.0.items');
});
