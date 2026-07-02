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
        'database.connections.bstore_catalog' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    DB::purge('bstore_order');
    DB::purge('bstore_catalog');

    foreach (['order_discounts', 'order_items', 'orders', 'cart_items', 'carts'] as $table) {
        Schema::connection('bstore_order')->dropIfExists($table);
    }

    foreach (['product_variants', 'products'] as $table) {
        Schema::connection('bstore_catalog')->dropIfExists($table);
    }

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
        $table->string('order_code', 191)->nullable()->unique();
        $table->string('receiver_name');
        $table->string('receiver_phone', 20);
        $table->string('receiver_email', 191)->nullable();
        $table->text('shipping_address');
        $table->string('shipping_method', 50);
        $table->string('payment_method', 50)->nullable();
        $table->decimal('total_amount', 15, 2)->default(0);
        $table->decimal('discount_amount', 15, 2)->default(0);
        $table->decimal('final_amount', 15, 2)->default(0);
        $table->string('status', 20)->nullable()->default('pending');
        $table->string('payment_status', 20)->nullable()->default('unpaid');
        $table->text('cancel_reason')->nullable();
        $table->text('note')->nullable();
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

    Schema::connection('bstore_order')->create('order_discounts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id')->index();
        $table->unsignedBigInteger('discount_id')->index();
        $table->string('discount_code', 191);
        $table->decimal('discount_amount', 15, 2)->default(0);
    });

    Schema::connection('bstore_catalog')->create('products', function (Blueprint $table) {
        $table->id();
        $table->decimal('price', 15, 2)->default(0);
        $table->decimal('sale_percent', 5, 2)->nullable();
        $table->decimal('sale_price', 15, 2)->nullable();
        $table->boolean('is_sale')->default(false);
    });

    Schema::connection('bstore_catalog')->create('product_variants', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_id')->index();
        $table->decimal('price', 15, 2)->default(0);
    });
});

test('cart items use sale price from catalog when product is on sale', function () {
    seedCatalogProduct(1, 10, 25000000, 22500000, true);

    $this->postJson('/api/carts', [
        'user_id' => 1,
        'items' => [
            [
                'product_variant_id' => 10,
                'product_name' => 'Sale Tablet',
                'price' => 25000000,
                'quantity' => 2,
                'subtotal' => 50000000,
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.price', '22500000.00')
        ->assertJsonPath('data.items.0.subtotal', '45000000.00');
});

test('cart items use catalog product price when product is not on sale', function () {
    seedCatalogProduct(2, 20, 12000000, null, false);

    $this->postJson('/api/carts', [
        'user_id' => 1,
        'items' => [
            [
                'product_variant_id' => 20,
                'product_name' => 'Regular Tablet',
                'price' => 1,
                'quantity' => 2,
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.price', '12000000.00')
        ->assertJsonPath('data.items.0.subtotal', '24000000.00');
});

test('zero sale price is ignored when calculating cart item price', function () {
    seedCatalogProduct(4, 40, 8989998, 0, true);

    $this->postJson('/api/carts', [
        'user_id' => 1,
        'items' => [
            [
                'product_variant_id' => 40,
                'product_name' => 'Zero Sale Tablet',
                'price' => 0,
                'quantity' => 1,
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.price', '8989998.00')
        ->assertJsonPath('data.items.0.subtotal', '8989998.00');
});

test('orders use sale price and recalculate totals from catalog pricing', function () {
    seedCatalogProduct(3, 30, 25000000, 22500000, true);

    $this->postJson('/api/orders', [
        'user_id' => 1,
        'receiver_name' => 'Nguyen Van A',
        'receiver_phone' => '0900000000',
        'shipping_address' => '123 Nguyen Trai',
        'shipping_method' => 'standard',
        'total_amount' => 50000000,
        'discount_amount' => 0,
        'final_amount' => 50000000,
        'items' => [
            [
                'product_variant_id' => 30,
                'product_name' => 'Sale Tablet',
                'price' => 25000000,
                'quantity' => 2,
                'subtotal' => 50000000,
            ],
        ],
        'discounts' => [
            [
                'discount_id' => 1,
                'discount_code' => 'SALE1M',
                'discount_amount' => 1000000,
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.price', '22500000.00')
        ->assertJsonPath('data.items.0.subtotal', '45000000.00')
        ->assertJsonPath('data.total_amount', '45000000.00')
        ->assertJsonPath('data.discount_amount', '1000000.00')
        ->assertJsonPath('data.final_amount', '44000000.00');
});

test('vnpay order is rejected when calculated final amount is below minimum', function () {
    $this->postJson('/api/orders', [
        'user_id' => 1,
        'receiver_name' => 'Nguyen Van A',
        'receiver_phone' => '0900000000',
        'shipping_address' => '123 Nguyen Trai',
        'shipping_method' => 'standard',
        'payment_method' => 'VNPAY',
        'items' => [
            [
                'product_variant_id' => 999,
                'product_name' => 'Broken Price Tablet',
                'price' => 0,
                'quantity' => 1,
            ],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('data.final_amount.0', 'Don hang thanh toan VNPAY phai co tong tien lon hon hoac bang 1000');

    expect(DB::connection('bstore_order')->table('orders')->count())->toBe(0);
});

function seedCatalogProduct(int $productId, int $variantId, float $price, ?float $salePrice, bool $isSale): void
{
    DB::connection('bstore_catalog')->table('products')->insert([
        'id' => $productId,
        'price' => $price,
        'sale_percent' => $isSale ? 10 : null,
        'sale_price' => $salePrice,
        'is_sale' => $isSale,
    ]);

    DB::connection('bstore_catalog')->table('product_variants')->insert([
        'id' => $variantId,
        'product_id' => $productId,
        'price' => $price,
    ]);
}
