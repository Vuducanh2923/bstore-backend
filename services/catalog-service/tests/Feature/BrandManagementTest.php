<?php

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'database.default' => 'bstore_catalog',
        'database.connections.bstore_catalog' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'services.cloudinary.cloud_name' => null,
        'services.cloudinary.api_key' => null,
        'services.cloudinary.api_secret' => null,
        'services.cloudinary.url' => null,
    ]);

    DB::purge('bstore_catalog');

    foreach (['product_images', 'products', 'categories', 'brands'] as $table) {
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
        $table->string('status', 20)->nullable()->default('active');
    });

    Schema::connection('bstore_catalog')->create('products', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('category_id')->index();
        $table->unsignedBigInteger('brand_id')->index();
        $table->string('name');
        $table->string('slug', 191)->unique();
        $table->decimal('price', 15, 2)->default(0);
        $table->string('status', 20)->nullable()->default('active');
        $table->timestamps();
    });

    Schema::connection('bstore_catalog')->create('product_images', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_id')->index();
        $table->string('image_url', 500);
        $table->boolean('is_thumbnail')->default(false);
    });

    DB::connection('bstore_catalog')->table('categories')->insert([
        'id' => 1,
        'name' => 'Phone',
        'slug' => 'phone',
        'status' => 'active',
    ]);
});

test('public brand api only returns active brands from database', function () {
    Brand::create([
        'name' => 'Apple',
        'slug' => 'apple',
        'logo' => 'https://cdn.example.test/apple.svg',
        'description' => 'Apple devices',
        'status' => 'active',
    ]);

    Brand::create([
        'name' => 'Samsung',
        'slug' => 'samsung',
        'status' => 'inactive',
    ]);

    $this->getJson('/api/brands')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Apple')
        ->assertJsonPath('data.0.slug', 'apple')
        ->assertJsonPath('data.0.logo', 'https://cdn.example.test/apple.svg')
        ->assertJsonPath('data.0.description', 'Apple devices')
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonMissingPath('pagination');
});

test('admin can create update search and toggle brands', function () {
    $createdId = $this->postJson('/api/admin/brands', [
        'name' => 'Apple',
        'logo' => 'https://cdn.example.test/apple.svg',
        'description' => 'Apple devices',
        'status' => 'active',
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Apple')
        ->assertJsonPath('data.slug', 'apple')
        ->assertJsonPath('data.logo', 'https://cdn.example.test/apple.svg')
        ->json('data.id');

    $this->getJson('/api/admin/brands?search=app&status=active&limit=5')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $createdId)
        ->assertJsonPath('pagination.limit', 5);

    $this->putJson("/api/admin/brands/{$createdId}", [
        'name' => 'Apple Vietnam',
        'slug' => '',
        'status' => 'inactive',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Apple Vietnam')
        ->assertJsonPath('data.slug', 'apple-vietnam')
        ->assertJsonPath('data.status', 'inactive');

    $this->patchJson("/api/admin/brands/{$createdId}/toggle-status")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

test('admin can upload brand logo file to public storage when cloudinary is not configured', function () {
    Storage::fake('public');

    $logo = UploadedFile::fake()->createWithContent(
        'brand.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
    );

    $this->post('/api/admin/brands', [
        'name' => 'Local Logo Brand',
        'logo' => $logo,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Local Logo Brand')
        ->assertJsonPath('data.status', 'active')
        ->assertJson(fn ($json) => $json
            ->where('success', true)
            ->whereType('data.logo', 'string')
            ->etc()
        );

    expect(Brand::first()->logo)->toContain('/storage/brands/');
});

test('admin cannot delete a brand that is used by products', function () {
    $usedBrand = Brand::create([
        'name' => 'Used Brand',
        'slug' => 'used-brand',
        'status' => 'active',
    ]);
    $unusedBrand = Brand::create([
        'name' => 'Unused Brand',
        'slug' => 'unused-brand',
        'status' => 'active',
    ]);

    Product::create([
        'category_id' => 1,
        'brand_id' => $usedBrand->id,
        'name' => 'Phone One',
        'slug' => 'phone-one',
        'price' => 100,
        'status' => 'active',
    ]);

    $this->deleteJson("/api/admin/brands/{$usedBrand->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Nhãn hàng đang được sử dụng.');

    $this->deleteJson("/api/admin/brands/{$unusedBrand->id}")
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('product list can be filtered by brand slug or brand id', function () {
    $apple = Brand::create([
        'name' => 'Apple',
        'slug' => 'apple',
        'logo' => 'https://cdn.example.test/apple.svg',
        'status' => 'active',
    ]);
    $samsung = Brand::create([
        'name' => 'Samsung',
        'slug' => 'samsung',
        'status' => 'active',
    ]);

    Product::create([
        'category_id' => 1,
        'brand_id' => $apple->id,
        'name' => 'iPhone',
        'price' => 100,
        'status' => 'active',
    ]);
    Product::create([
        'category_id' => 1,
        'brand_id' => $samsung->id,
        'name' => 'Galaxy',
        'price' => 90,
        'status' => 'active',
    ]);

    $this->getJson('/api/products?brand=apple')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'iPhone')
        ->assertJsonPath('data.0.brand.id', $apple->id)
        ->assertJsonPath('data.0.brand.slug', 'apple')
        ->assertJsonPath('data.0.brand.logo', 'https://cdn.example.test/apple.svg');

    $this->getJson("/api/products?brand_id={$samsung->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Galaxy')
        ->assertJsonPath('data.0.brand.id', $samsung->id);
});
