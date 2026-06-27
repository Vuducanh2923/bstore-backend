<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->testStoragePath = base_path('storage/framework/testing/home-banners');

    File::deleteDirectory($this->testStoragePath);
    File::ensureDirectoryExists($this->testStoragePath.'/app');

    $this->app->useStoragePath($this->testStoragePath);

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
});

afterEach(function () {
    $this->app->useStoragePath(base_path('storage'));
    File::deleteDirectory($this->testStoragePath);
});

test('home banners endpoint creates default json file when missing', function () {
    $bannerPath = storage_path('app/home-banners.json');

    expect(File::exists($bannerPath))->toBeFalse();

    $this->getJson('/api/home/banners')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.hero_main.0.title', 'Flash Sale 12h')
        ->assertJsonPath('data.hero_right_top.0.link', '/sale')
        ->assertJsonPath('data.hero_right_bottom.0.image_url', 'https://res.cloudinary.com/demo/image/upload/c_fill,w_600,h_250,q_auto,f_auto/bike.jpg');

    expect(File::exists($bannerPath))->toBeTrue();
});

test('home banners endpoint only returns active banners sorted by sort order', function () {
    File::put(storage_path('app/home-banners.json'), json_encode([
        'hero_main' => [
            [
                'title' => 'Inactive',
                'image_url' => '/uploads/banners/inactive.jpg',
                'link' => '/inactive',
                'status' => false,
                'sort_order' => 1,
            ],
            [
                'title' => 'Second',
                'image_url' => '/uploads/banners/second.jpg',
                'link' => '/second',
                'status' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'First',
                'image_url' => '/uploads/banners/first.jpg',
                'link' => '/first',
                'status' => true,
                'sort_order' => 1,
            ],
        ],
        'hero_right_top' => [],
        'hero_right_bottom' => [
            [
                'title' => 'Bottom',
                'image_url' => '/uploads/banners/bottom.jpg',
                'link' => '/bottom',
                'status' => true,
                'sort_order' => 1,
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $this->getJson('/api/home/banners')
        ->assertOk()
        ->assertJsonCount(2, 'data.hero_main')
        ->assertJsonPath('data.hero_main.0.title', 'First')
        ->assertJsonPath('data.hero_main.0.image_url', '/uploads/banners/first.jpg')
        ->assertJsonPath('data.hero_main.1.title', 'Second')
        ->assertJsonPath('data.hero_right_top', [])
        ->assertJsonPath('data.hero_right_bottom.0.title', 'Bottom')
        ->assertJsonPath('data.hero_right_bottom.0.image_url', '/uploads/banners/bottom.jpg');
});

test('home banners endpoint returns active banners from database before json fallback', function () {
    createHomeBannerTable();

    DB::connection('bstore_catalog')->table('banners')->insert([
        [
            'title' => 'Cloudinary main banner',
            'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/admin-main.jpg',
            'public_id' => 'bstore/banners/admin-main',
            'route' => '/products',
            'display_slot' => 1,
            'sort_order' => 1,
            'status' => true,
        ],
        [
            'title' => 'Cloudinary right banner',
            'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/admin-right.jpg',
            'public_id' => 'bstore/banners/admin-right',
            'route' => '/sale',
            'display_slot' => 2,
            'sort_order' => 1,
            'status' => true,
        ],
        [
            'title' => 'Inactive banner',
            'image_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/inactive.jpg',
            'public_id' => 'bstore/banners/inactive',
            'route' => '/hidden',
            'display_slot' => 3,
            'sort_order' => 1,
            'status' => false,
        ],
    ]);

    $this->getJson('/api/banners/home')
        ->assertOk()
        ->assertJsonPath('data.hero_main.0.title', 'Cloudinary main banner')
        ->assertJsonPath('data.hero_main.0.image_url', 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/admin-main.jpg')
        ->assertJsonPath('data.hero_main.0.public_id', 'bstore/banners/admin-main')
        ->assertJsonPath('data.hero_main.0.link', '/products')
        ->assertJsonPath('data.hero_right_top.0.image_url', 'https://res.cloudinary.com/demo/image/upload/v1/bstore/banners/admin-right.jpg')
        ->assertJsonPath('data.hero_right_bottom', []);
});

test('home banners alias under banners path reads the json config', function () {
    File::put(storage_path('app/home-banners.json'), json_encode([
        'hero_main' => [
            [
                'title' => 'Home alias',
                'image_url' => '/uploads/banners/home-alias.jpg',
                'link' => '/home-alias',
                'status' => true,
                'sort_order' => 1,
            ],
        ],
        'hero_right_top' => [],
        'hero_right_bottom' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $this->getJson('/api/banners/home')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.hero_main.0.title', 'Home alias')
        ->assertJsonPath('data.hero_right_top', [])
        ->assertJsonPath('data.hero_right_bottom', []);
});

function createHomeBannerTable(): void
{
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
}
