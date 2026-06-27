<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\CloudinaryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function fakeProductImage(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'product.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
    );
}

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
        $table->text('description')->nullable();
        $table->string('status', 20)->nullable()->default('active');
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
        $table->string('status', 20)->nullable()->default('active');
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
        $table->string('public_id')->nullable();
        $table->boolean('is_thumbnail')->default(false);
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

test('admin upload product image returns cloudinary url and public id', function () {
    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('uploadProductImage')
            ->once()
            ->andReturn([
                'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/products/uploaded.jpg',
                'public_id' => 'bstore/products/uploaded',
            ]);
    });

    $this->post('/api/uploads/images', [
        'image' => fakeProductImage(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.image_url', 'https://res.cloudinary.com/demo/image/upload/v1/bstore/products/uploaded.jpg')
        ->assertJsonPath('data.url', 'https://res.cloudinary.com/demo/image/upload/v1/bstore/products/uploaded.jpg')
        ->assertJsonPath('data.public_id', 'bstore/products/uploaded');
});

test('admin can create a product with a cloudinary product image public id', function () {
    $this->postJson('/api/products', [
        ...cloudinaryProductPayload('Lenovo Tab P12'),
        'images' => [
            [
                'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/products/new.jpg',
                'public_id' => 'bstore/products/new',
                'is_thumbnail' => true,
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.images.0.image_url', 'https://res.cloudinary.com/demo/image/upload/v1/bstore/products/new.jpg')
        ->assertJsonPath('data.images.0.public_id', 'bstore/products/new');

    $this->assertDatabaseHas('product_images', [
        'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/products/new.jpg',
        'public_id' => 'bstore/products/new',
        'is_thumbnail' => true,
    ], 'bstore_catalog');
});

test('admin replacing product images deletes old cloudinary images', function () {
    $product = Product::create(cloudinaryProductPayload('Lenovo Tab P12'));
    ProductImage::create([
        'product_id' => $product->id,
        'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/products/old.jpg',
        'public_id' => 'bstore/products/old',
        'is_thumbnail' => true,
    ]);

    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('deleteImage')
            ->once()
            ->with('bstore/products/old');
    });

    $this->putJson("/api/products/{$product->id}", [
        'images' => [
            [
                'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/products/new.jpg',
                'public_id' => 'bstore/products/new',
                'is_thumbnail' => true,
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.images.0.public_id', 'bstore/products/new');

    $this->assertDatabaseMissing('product_images', [
        'product_id' => $product->id,
        'public_id' => 'bstore/products/old',
    ], 'bstore_catalog');
    $this->assertDatabaseHas('product_images', [
        'product_id' => $product->id,
        'public_id' => 'bstore/products/new',
    ], 'bstore_catalog');
});

test('admin delete product removes cloudinary images before deleting records', function () {
    $product = Product::create(cloudinaryProductPayload('Lenovo Tab P12'));
    ProductImage::create([
        'product_id' => $product->id,
        'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/products/delete.jpg',
        'public_id' => 'bstore/products/delete',
        'is_thumbnail' => true,
    ]);

    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('deleteImage')
            ->once()
            ->with('bstore/products/delete');
    });

    $this->deleteJson("/api/products/{$product->id}")
        ->assertOk();

    $this->assertDatabaseMissing('product_images', [
        'product_id' => $product->id,
    ], 'bstore_catalog');
    $this->assertDatabaseMissing('products', [
        'id' => $product->id,
    ], 'bstore_catalog');
});

function cloudinaryProductPayload(string $name): array
{
    return [
        'category_id' => 1,
        'brand_id' => 1,
        'name' => $name,
        'price' => 12000000,
        'status' => 'active',
    ];
}
