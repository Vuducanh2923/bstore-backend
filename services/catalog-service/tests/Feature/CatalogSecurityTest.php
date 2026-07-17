<?php

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
    Schema::connection('bstore_catalog')->create('categories', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('slug', 191)->unique();
        $table->text('description')->nullable();
        $table->string('icon', 500)->nullable();
        $table->string('status', 20)->default('active');
    });
});

test('public catalog reads remain available without a jwt', function () {
    DB::connection('bstore_catalog')->table('categories')->insert([
        'name' => 'Phone',
        'slug' => 'phone',
        'status' => 'active',
    ]);

    $this->withoutCatalogToken()
        ->getJson('/api/categories')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'phone');
});

test('catalog mutations deny missing and non admin jwt tokens', function () {
    $this->withoutCatalogToken()
        ->postJson('/api/categories', [
            'name' => 'Phone',
            'slug' => 'phone',
        ])
        ->assertUnauthorized();

    $this->withToken($this->tokenForRole('CUSTOMER'))
        ->postJson('/api/categories', [
            'name' => 'Phone',
            'slug' => 'phone',
        ])
        ->assertForbidden();
});

test('admin jwt can access an explicitly declared mutation', function () {
    $this->postJson('/api/categories', [
        'name' => 'Phone',
        'slug' => 'phone',
        'status' => 'active',
    ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'phone');
});

test('unknown generic resources are not routable', function () {
    $this->postJson('/api/users', ['name' => 'attacker'])
        ->assertNotFound();

    $this->getJson('/api/inventory_transactions')
        ->assertNotFound();
});

test('internal inventory endpoints require the dedicated service token', function () {
    $payload = [
        'reference' => 'ORDER-100',
        'items' => [['product_variant_id' => 1, 'quantity' => 1]],
    ];

    $this->postJson('/api/internal/inventory/reservations', $payload)
        ->assertUnauthorized();

    $this->withHeader('X-Internal-Service-Token', 'wrong-token')
        ->postJson('/api/internal/inventory/reservations', $payload)
        ->assertUnauthorized();
});
