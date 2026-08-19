<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
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

    foreach (['products', 'categories', 'brands'] as $table) {
        Schema::connection('bstore_catalog')->dropIfExists($table);
    }

    Schema::connection('bstore_catalog')->create('brands', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('slug', 191)->unique();
        $table->string('logo', 500)->nullable();
        $table->string('status', 20)->default('active');
    });

    Schema::connection('bstore_catalog')->create('categories', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('slug', 191)->unique();
        $table->string('status', 20)->default('active');
    });

    Schema::connection('bstore_catalog')->create('products', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('category_id');
        $table->unsignedBigInteger('brand_id');
        $table->string('name');
        $table->string('status', 20)->default('active');
    });

    DB::connection('bstore_catalog')->table('categories')->insert([
        ['id' => 1, 'name' => 'Phones', 'slug' => 'phones', 'status' => 'active'],
        ['id' => 2, 'name' => 'Hidden', 'slug' => 'hidden', 'status' => 'inactive'],
    ]);
    DB::connection('bstore_catalog')->table('brands')->insert([
        ['id' => 1, 'name' => 'Apple', 'slug' => 'apple', 'logo' => 'https://cdn.test/apple.svg', 'status' => 'active'],
        ['id' => 2, 'name' => 'Hidden Brand', 'slug' => 'hidden-brand', 'logo' => 'https://cdn.test/hidden.svg', 'status' => 'inactive'],
    ]);
    DB::connection('bstore_catalog')->table('products')->insert([
        ['id' => 1, 'category_id' => 1, 'brand_id' => 1, 'name' => 'Phone', 'status' => 'active'],
        ['id' => 2, 'category_id' => 1, 'brand_id' => 2, 'name' => 'Hidden phone', 'status' => 'active'],
    ]);
});

test('category menu can include active brand logos without product requests', function (): void {
    $this->withoutCatalogToken()
        ->getJson('/api/categories?include=brands')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'phones')
        ->assertJsonPath('data.0.brands.0.name', 'Apple')
        ->assertJsonPath('data.0.brands.0.logo', 'https://cdn.test/apple.svg')
        ->assertJsonCount(1, 'data.0.brands');
});

test('category menu keeps the lightweight response by default', function (): void {
    $this->withoutCatalogToken()
        ->getJson('/api/categories')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'phones')
        ->assertJsonMissingPath('data.0.brands');
});
