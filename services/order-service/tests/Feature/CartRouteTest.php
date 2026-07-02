<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'database.default' => 'bstore_order',
        'database.connections.bstore_order' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    DB::purge('bstore_order');

    Schema::connection('bstore_order')->create('carts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->index();
        $table->string('status', 20)->nullable()->default('active');
    });

    Schema::connection('bstore_order')->create('cart_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('cart_id')->index();
        $table->unsignedBigInteger('product_variant_id')->index();
        $table->string('product_name');
        $table->string('color', 50)->nullable();
        $table->string('ram', 50)->nullable();
        $table->string('storage', 50)->nullable();
        $table->decimal('price', 15, 2)->default(0);
        $table->unsignedInteger('quantity')->default(1);
        $table->decimal('subtotal', 15, 2)->default(0);
    });

    Schema::connection('bstore_order')->create('orders', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->index();
    });
});

test('cart detail route returns a cart with items', function () {
    $cartId = DB::connection('bstore_order')->table('carts')->insertGetId([
        'user_id' => 10,
        'status' => 'active',
    ]);

    DB::connection('bstore_order')->table('cart_items')->insert([
        'cart_id' => $cartId,
        'product_variant_id' => 99,
        'product_name' => 'Tablet',
        'price' => 1000000,
        'quantity' => 2,
        'subtotal' => 2000000,
    ]);

    $this->getJson("/api/carts/{$cartId}")
        ->assertOk()
        ->assertJsonPath('data.id', $cartId)
        ->assertJsonPath('data.user_id', 10)
        ->assertJsonCount(1, 'data.items');
});

test('cart detail route returns not found for a missing cart', function () {
    $this->getJson('/api/carts/999')
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

test('internal paid order cart clear removes active cart items for order user', function () {
    $orderId = DB::connection('bstore_order')->table('orders')->insertGetId([
        'user_id' => 10,
    ]);

    $activeCartId = DB::connection('bstore_order')->table('carts')->insertGetId([
        'user_id' => 10,
        'status' => 'active',
    ]);
    $inactiveCartId = DB::connection('bstore_order')->table('carts')->insertGetId([
        'user_id' => 10,
        'status' => 'checked_out',
    ]);
    $otherUserCartId = DB::connection('bstore_order')->table('carts')->insertGetId([
        'user_id' => 11,
        'status' => 'active',
    ]);

    insertCartItem($activeCartId, 99);
    insertCartItem($activeCartId, 100);
    insertCartItem($inactiveCartId, 101);
    insertCartItem($otherUserCartId, 102);

    $this->postJson("/api/internal/orders/{$orderId}/cart/clear")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.order_id', $orderId)
        ->assertJsonPath('data.user_id', 10)
        ->assertJsonPath('data.deleted_items', 2);

    expect(DB::connection('bstore_order')->table('cart_items')->where('cart_id', $activeCartId)->count())->toBe(0)
        ->and(DB::connection('bstore_order')->table('cart_items')->where('cart_id', $inactiveCartId)->count())->toBe(1)
        ->and(DB::connection('bstore_order')->table('cart_items')->where('cart_id', $otherUserCartId)->count())->toBe(1);
});

test('internal paid order cart clear is idempotent', function () {
    $orderId = DB::connection('bstore_order')->table('orders')->insertGetId([
        'user_id' => 10,
    ]);
    $cartId = DB::connection('bstore_order')->table('carts')->insertGetId([
        'user_id' => 10,
        'status' => 'active',
    ]);

    insertCartItem($cartId, 99);

    $this->postJson("/api/internal/orders/{$orderId}/cart/clear")
        ->assertOk()
        ->assertJsonPath('data.deleted_items', 1);

    $this->postJson("/api/internal/orders/{$orderId}/cart/clear")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.deleted_items', 0);
});

test('internal paid order cart clear returns not found for missing order', function () {
    $this->postJson('/api/internal/orders/999/cart/clear')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.order_found', false);
});

function insertCartItem(int $cartId, int $variantId): void
{
    DB::connection('bstore_order')->table('cart_items')->insert([
        'cart_id' => $cartId,
        'product_variant_id' => $variantId,
        'product_name' => "Product {$variantId}",
        'price' => 1000000,
        'quantity' => 1,
        'subtotal' => 1000000,
    ]);
}
