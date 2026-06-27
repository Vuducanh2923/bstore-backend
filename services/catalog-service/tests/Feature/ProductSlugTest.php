<?php

use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'database.default' => 'bstore_catalog',
        'database.connections.bstore_catalog' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    DB::purge('bstore_catalog');

    foreach ([
        'inventories',
        'product_images',
        'product_variants',
        'products',
        'warranty_policies',
        'categories',
        'brands',
    ] as $table) {
        Schema::connection('bstore_catalog')->dropIfExists($table);
    }

    Schema::connection('bstore_catalog')->create('brands', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug', 191)->unique();
        $table->string('logo', 500)->nullable();
        $table->text('description')->nullable();
        $table->string('status', 20)->nullable()->default('active');
        $table->timestamps();
    });

    Schema::connection('bstore_catalog')->create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug', 191)->unique();
        $table->text('description')->nullable();
        $table->string('status', 20)->nullable()->default('active');
    });

    Schema::connection('bstore_catalog')->create('warranty_policies', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('duration_months')->default(0);
        $table->unsignedInteger('return_days')->default(0);
        $table->unsignedInteger('exchange_days')->default(0);
        $table->boolean('repair_support')->default(false);
        $table->text('description')->nullable();
        $table->string('status', 20)->nullable()->default('active');
    });

    Schema::connection('bstore_catalog')->create('products', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('category_id')->index();
        $table->unsignedBigInteger('brand_id')->index();
        $table->unsignedBigInteger('warranty_policy_id')->nullable()->index();
        $table->string('name');
        $table->string('slug', 191)->unique();
        $table->longText('description')->nullable();
        $table->json('specifications')->nullable();
        $table->decimal('price', 15, 2)->default(0);
        $table->decimal('sale_percent', 5, 2)->nullable();
        $table->decimal('discount_percent', 5, 2)->nullable();
        $table->decimal('sale_price', 15, 2)->nullable();
        $table->boolean('is_sale')->default(false);
        $table->string('status', 20)->nullable()->default('active');
        $table->timestamps();
    });

    Schema::connection('bstore_catalog')->create('product_variants', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_id')->index();
        $table->string('color', 50)->nullable();
        $table->string('ram', 50)->nullable();
        $table->string('storage', 50)->nullable();
        $table->decimal('price', 15, 2)->default(0);
        $table->string('sku', 191)->unique();
        $table->string('barcode', 191)->nullable();
        $table->string('status', 20)->nullable()->default('active');
    });

    Schema::connection('bstore_catalog')->create('product_images', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_id')->index();
        $table->unsignedBigInteger('product_variant_id')->nullable()->index();
        $table->string('image_url', 500);
        $table->boolean('is_thumbnail')->default(false);
    });

    Schema::connection('bstore_catalog')->create('inventories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_variant_id')->unique();
        $table->integer('quantity')->default(0);
        $table->integer('reserved_quantity')->default(0);
    });

    DB::connection('bstore_catalog')->table('brands')->insert([
        'id' => 1,
        'name' => 'Lenovo',
        'slug' => 'lenovo',
        'status' => 'active',
    ]);

    DB::connection('bstore_catalog')->table('categories')->insert([
        'id' => 1,
        'name' => 'Tablet',
        'slug' => 'tablet',
        'status' => 'active',
    ]);
});

test('product slugs are generated from names and stay unique', function () {
    $first = Product::create(productPayload('Lenovo Tab P12'));
    $second = Product::create(productPayload('Lenovo Tab P12'));

    expect($first->slug)->toBe('lenovo-tab-p12')
        ->and($second->slug)->toBe('lenovo-tab-p12-2');
});

test('product slug is regenerated when the product name changes', function () {
    Product::create(productPayload('Lenovo Tab P12 Special Edition'));
    $product = Product::create(productPayload('Lenovo Tab P12'));

    $product->update(['name' => 'Lenovo Tab P12 Special Edition']);

    expect($product->fresh()->slug)->toBe('lenovo-tab-p12-special-edition-2');
});

test('product detail is found by slug and id', function () {
    $product = Product::create(productPayload('Lenovo Tab P12 Special Edition'));

    $this->getJson("/api/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', 'lenovo-tab-p12-special-edition');

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.slug', 'lenovo-tab-p12-special-edition')
        ->assertJsonPath('data.brand.id', 1)
        ->assertJsonPath('data.brand.name', 'Lenovo')
        ->assertJsonPath('data.brand.slug', 'lenovo')
        ->assertJsonPath('data.brand.logo', null);
});

test('product sale price is calculated when creating and updating a product', function () {
    $response = $this->postJson('/api/products', [
        ...productPayload('Sale Tablet'),
        'price' => 25000000,
        'sale_percent' => 10,
    ]);

    $productId = $response
        ->assertCreated()
        ->assertJsonPath('data.price', '25000000.00')
        ->assertJsonPath('data.sale_percent', '10.00')
        ->assertJsonPath('data.discount_percent', '10.00')
        ->assertJsonPath('data.sale_price', '22500000.00')
        ->assertJsonPath('data.is_sale', true)
        ->json('data.id');

    $this->patchJson("/api/products/{$productId}", [
        'price' => 30000000,
    ])
        ->assertOk()
        ->assertJsonPath('data.price', '30000000.00')
        ->assertJsonPath('data.sale_percent', '10.00')
        ->assertJsonPath('data.discount_percent', '10.00')
        ->assertJsonPath('data.sale_price', '27000000.00')
        ->assertJsonPath('data.is_sale', true);

    $this->patchJson("/api/products/{$productId}", [
        'discount_percent' => 0,
    ])
        ->assertOk()
        ->assertJsonPath('data.sale_percent', null)
        ->assertJsonPath('data.discount_percent', null)
        ->assertJsonPath('data.sale_price', null)
        ->assertJsonPath('data.is_sale', false);
});

test('product list is paginated and only returns summary fields', function () {
    Product::create([
        ...productPayload('Premium Tablet'),
        'description' => 'Long product description that should not be returned in the list API.',
        'specifications' => ['cpu' => 'A1'],
        'price' => 20000000,
    ]);

    $budgetTablet = Product::create([
        ...productPayload('Budget Tablet'),
        'description' => 'Another long product description.',
        'specifications' => ['cpu' => 'B1'],
        'price' => 10000000,
    ]);

    DB::connection('bstore_catalog')->table('product_images')->insert([
        'product_id' => $budgetTablet->id,
        'image_url' => 'https://cdn.example.test/budget-tablet.webp',
        'is_thumbnail' => true,
    ]);

    $this->getJson('/api/products?page=1&limit=1&category=tablet&sort=price_asc')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $budgetTablet->id)
        ->assertJsonPath('data.0.name', 'Budget Tablet')
        ->assertJsonPath('data.0.slug', 'budget-tablet')
        ->assertJsonPath('data.0.price', '10000000.00')
        ->assertJsonPath('data.0.original_price', '10000000.00')
        ->assertJsonPath('data.0.sale_percent', null)
        ->assertJsonPath('data.0.discount_percent', null)
        ->assertJsonPath('data.0.sale_price', null)
        ->assertJsonPath('data.0.discounted_price', null)
        ->assertJsonPath('data.0.is_sale', false)
        ->assertJsonPath('data.0.thumbnail', 'https://cdn.example.test/budget-tablet.webp')
        ->assertJsonPath('data.0.category_id', 1)
        ->assertJsonPath('data.0.category_name', 'Tablet')
        ->assertJsonPath('data.0.brand_id', 1)
        ->assertJsonPath('data.0.brand_name', 'Lenovo')
        ->assertJsonPath('data.0.brand.id', 1)
        ->assertJsonPath('data.0.brand.name', 'Lenovo')
        ->assertJsonPath('data.0.brand.slug', 'lenovo')
        ->assertJsonPath('data.0.brand.logo', null)
        ->assertJsonPath('data.0.rating', null)
        ->assertJsonPath('pagination.page', 1)
        ->assertJsonPath('pagination.limit', 1)
        ->assertJsonPath('pagination.total', 2)
        ->assertJsonPath('pagination.totalPages', 2)
        ->assertJsonMissingPath('meta')
        ->assertJsonMissingPath('data.0.description')
        ->assertJsonMissingPath('data.0.specifications')
        ->assertJsonMissingPath('data.0.images')
        ->assertJsonMissingPath('data.0.variants')
        ->assertJsonMissingPath('data.0.category');
});

test('sale product list only returns sale products newest first', function () {
    Product::create(productPayload('Regular Tablet'));
    $olderSale = Product::create([
        ...productPayload('Older Sale Tablet'),
        'sale_percent' => 5,
        'sale_price' => 11400000,
        'is_sale' => true,
    ]);
    $newerSale = Product::create([
        ...productPayload('Newer Sale Tablet'),
        'sale_percent' => 10,
        'sale_price' => 10800000,
        'is_sale' => true,
    ]);

    $this->getJson('/api/products/sale')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Success')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newerSale->id)
        ->assertJsonPath('data.0.original_price', '12000000.00')
        ->assertJsonPath('data.0.discount_percent', '10.00')
        ->assertJsonPath('data.0.discounted_price', '10800000.00')
        ->assertJsonPath('data.0.is_sale', true)
        ->assertJsonPath('data.1.id', $olderSale->id)
        ->assertJsonPath('data.1.discount_percent', '5.00')
        ->assertJsonPath('data.1.is_sale', true);
});

test('category and brand header APIs only return active records', function () {
    DB::connection('bstore_catalog')->table('categories')->insert([
        'id' => 2,
        'name' => 'Locked Category',
        'slug' => 'locked-category',
        'status' => 'locked',
    ]);

    DB::connection('bstore_catalog')->table('brands')->insert([
        'id' => 2,
        'name' => 'Locked Brand',
        'slug' => 'locked-brand',
        'status' => 'locked',
    ]);

    $this->getJson('/api/categories')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', 1)
        ->assertJsonPath('data.0.name', 'Tablet')
        ->assertJsonPath('data.0.slug', 'tablet')
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonMissingPath('data.0.description');

    $this->getJson('/api/brands')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', 1)
        ->assertJsonPath('data.0.name', 'Lenovo')
        ->assertJsonPath('data.0.slug', 'lenovo')
        ->assertJsonPath('data.0.logo', null)
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.description', null);
});

test('new product list returns latest active category and brand products only', function () {
    DB::connection('bstore_catalog')->table('categories')->insert([
        'id' => 2,
        'name' => 'Locked Category',
        'slug' => 'locked-category',
        'status' => 'locked',
    ]);

    DB::connection('bstore_catalog')->table('brands')->insert([
        'id' => 2,
        'name' => 'Locked Brand',
        'slug' => 'locked-brand',
        'status' => 'locked',
    ]);

    Product::create([...productPayload('Hidden Locked Category Product'), 'category_id' => 2]);
    Product::create([...productPayload('Hidden Locked Brand Product'), 'brand_id' => 2]);

    foreach (range(1, 25) as $number) {
        Product::create(productPayload("New Product {$number}"));
    }

    $this->getJson('/api/products/new')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Success')
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('data.0.name', 'New Product 25')
        ->assertJsonPath('data.19.name', 'New Product 6')
        ->assertJsonPath('pagination.limit', 20)
        ->assertJsonPath('pagination.total', 25);

    $this->getJson('/api/products?category=2')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/products?brand=2')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('slug migration adds and backfills slugs for existing products', function () {
    foreach ([
        'inventories',
        'product_images',
        'product_variants',
        'products',
    ] as $table) {
        Schema::connection('bstore_catalog')->dropIfExists($table);
    }

    Schema::connection('bstore_catalog')->create('products', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('category_id')->index();
        $table->unsignedBigInteger('brand_id')->index();
        $table->unsignedBigInteger('warranty_policy_id')->nullable()->index();
        $table->string('name');
        $table->longText('description')->nullable();
        $table->json('specifications')->nullable();
        $table->decimal('price', 15, 2)->default(0);
        $table->string('status', 20)->nullable()->default('active');
    });

    DB::connection('bstore_catalog')->table('products')->insert([
        productRow('Lenovo Tab P12'),
        productRow('Lenovo Tab P12'),
        productRow('Lenovo Tab P12 Special Edition'),
    ]);

    $migration = include base_path('database/migrations/2026_06_26_000001_add_slug_to_products_table.php');
    $migration->up();

    $slugs = DB::connection('bstore_catalog')
        ->table('products')
        ->orderBy('id')
        ->pluck('slug')
        ->all();

    expect(Schema::connection('bstore_catalog')->hasColumn('products', 'slug'))->toBeTrue()
        ->and($slugs)->toBe([
            'lenovo-tab-p12',
            'lenovo-tab-p12-2',
            'lenovo-tab-p12-special-edition',
        ]);
});

function productPayload(string $name): array
{
    return [
        'category_id' => 1,
        'brand_id' => 1,
        'name' => $name,
        'price' => 12000000,
        'status' => 'active',
    ];
}

function productRow(string $name): array
{
    return [
        'category_id' => 1,
        'brand_id' => 1,
        'name' => $name,
        'price' => 12000000,
        'status' => 'active',
    ];
}
