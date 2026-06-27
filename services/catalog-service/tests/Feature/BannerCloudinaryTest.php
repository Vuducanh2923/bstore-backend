<?php

use App\Models\Banner;
use App\Services\CloudinaryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function fakeBannerImage(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'banner.png',
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

    Schema::connection('bstore_catalog')->dropIfExists('banners');
    Schema::connection('bstore_catalog')->create('banners', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('subtitle')->nullable();
        $table->text('description')->nullable();
        $table->string('button_text')->nullable();
        $table->string('button_link')->nullable();
        $table->string('image_url', 500);
        $table->string('public_id')->nullable();
        $table->string('route', 255)->nullable();
        $table->unsignedTinyInteger('display_slot')->default(1);
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });
});

test('admin can create a banner with a manual image url', function () {
    $imageUrl = 'https://cdn.example.test/banner.jpg';

    $this->postJson('/api/banners', [
        'title' => 'Manual URL',
        'image_url' => $imageUrl,
        'status' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.image_url', $imageUrl)
        ->assertJsonPath('data.public_id', null);

    $this->assertDatabaseHas('banners', [
        'title' => 'Manual URL',
        'image_url' => $imageUrl,
        'public_id' => null,
    ], 'bstore_catalog');
});

test('admin can assign and update a banner display slot', function () {
    $imageUrl = 'https://cdn.example.test/banner-slot.jpg';

    $response = $this->postJson('/api/banners', [
        'title' => 'Slot banner',
        'image_url' => $imageUrl,
        'display_slot' => 2,
        'status' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.display_slot', 2);

    $bannerId = $response->json('data.id');

    $this->putJson("/api/banners/{$bannerId}", [
        'display_slot' => 3,
    ])
        ->assertOk()
        ->assertJsonPath('data.display_slot', 3);

    $this->assertDatabaseHas('banners', [
        'id' => $bannerId,
        'display_slot' => 3,
    ], 'bstore_catalog');
});

test('admin can create a banner by uploading an image to cloudinary', function () {
    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('uploadBannerImage')
            ->once()
            ->andReturn([
                'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/new.jpg',
                'public_id' => 'bstore/banners/new',
            ]);
    });

    $this->post('/api/banners', [
        'title' => 'Uploaded',
        'image' => fakeBannerImage(),
        'status' => 'true',
    ])
        ->assertCreated()
        ->assertJsonPath('data.image_url', 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/new.jpg')
        ->assertJsonPath('data.public_id', 'bstore/banners/new');

    $this->assertDatabaseHas('banners', [
        'title' => 'Uploaded',
        'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/new.jpg',
        'public_id' => 'bstore/banners/new',
    ], 'bstore_catalog');
});

test('admin can replace a banner image and old cloudinary image is deleted', function () {
    $banner = Banner::create([
        'title' => 'Replace me',
        'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/old.jpg',
        'public_id' => 'bstore/banners/old',
        'status' => true,
    ]);

    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('uploadBannerImage')
            ->once()
            ->andReturn([
                'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/new.jpg',
                'public_id' => 'bstore/banners/new',
            ]);
        $mock->shouldReceive('deleteImage')
            ->once()
            ->with('bstore/banners/old');
    });

    $this->post("/api/banners/{$banner->id}", [
        'title' => 'Replaced',
        'image' => fakeBannerImage(),
        'status' => 'true',
    ])
        ->assertOk()
        ->assertJsonPath('data.image_url', 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/new.jpg')
        ->assertJsonPath('data.public_id', 'bstore/banners/new');

    $this->assertDatabaseHas('banners', [
        'id' => $banner->id,
        'title' => 'Replaced',
        'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/new.jpg',
        'public_id' => 'bstore/banners/new',
    ], 'bstore_catalog');
});

test('admin delete removes cloudinary image before deleting banner record', function () {
    $banner = Banner::create([
        'title' => 'Delete me',
        'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/delete.jpg',
        'public_id' => 'bstore/banners/delete',
        'status' => true,
    ]);

    $this->mock(CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('deleteImage')
            ->once()
            ->with('bstore/banners/delete');
    });

    $this->deleteJson("/api/banners/{$banner->id}")
        ->assertOk();

    $this->assertDatabaseMissing('banners', [
        'id' => $banner->id,
    ], 'bstore_catalog');
});

test('banner list returns the full image url directly', function () {
    $imageUrl = 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/list.jpg';

    Banner::create([
        'title' => 'List me',
        'image_url' => $imageUrl,
        'public_id' => 'bstore/banners/list',
        'status' => true,
    ]);

    $this->getJson('/api/banners')
        ->assertOk()
        ->assertJsonPath('data.0.image_url', $imageUrl);
});

test('banner list maps legacy local banner paths to working cloudinary urls', function () {
    Banner::create([
        'title' => 'Legacy local path',
        'image_url' => '/uploads/banners/flash-sale.jpg',
        'status' => true,
    ]);

    $this->getJson('/api/banners')
        ->assertOk()
        ->assertJsonPath('data.0.image_url', 'https://res.cloudinary.com/demo/image/upload/c_fill,w_1200,h_420,q_auto,f_auto/sample.jpg');
});
