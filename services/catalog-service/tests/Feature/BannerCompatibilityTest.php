<?php

use App\Models\Banner;
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

    Schema::connection('bstore_catalog')->dropIfExists('banners');
    Schema::connection('bstore_catalog')->create('banners', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('image_url', 500);
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });
});

test('banner list works when display slot column does not exist', function () {
    Banner::create([
        'title' => 'Second',
        'image_url' => 'https://cdn.example.test/second.jpg',
        'sort_order' => 2,
        'status' => true,
    ]);

    Banner::create([
        'title' => 'First',
        'image_url' => 'https://cdn.example.test/first.jpg',
        'sort_order' => 1,
        'status' => true,
    ]);

    $this->getJson('/api/banners')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'First')
        ->assertJsonPath('data.1.title', 'Second');
});

test('banner update ignores display slot when column does not exist', function () {
    $banner = Banner::create([
        'title' => 'No slot column',
        'image_url' => 'https://cdn.example.test/banner.jpg',
        'sort_order' => 1,
        'status' => true,
    ]);

    $this->putJson("/api/banners/{$banner->id}", [
        'display_slot' => 3,
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'No slot column');

    $this->assertDatabaseHas('banners', [
        'id' => $banner->id,
        'title' => 'No slot column',
    ], 'bstore_catalog');
});

test('banner create ignores display slot when column does not exist', function () {
    $this->postJson('/api/banners', [
        'title' => 'Created without slot column',
        'image_url' => 'https://cdn.example.test/banner.jpg',
        'display_slot' => 2,
        'status' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Created without slot column');

    $this->assertDatabaseHas('banners', [
        'title' => 'Created without slot column',
        'image_url' => 'https://cdn.example.test/banner.jpg',
    ], 'bstore_catalog');
});
