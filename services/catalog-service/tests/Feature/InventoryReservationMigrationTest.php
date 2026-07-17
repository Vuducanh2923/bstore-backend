<?php

use Illuminate\Database\QueryException;
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
    Schema::connection('bstore_catalog')->create('inventory_transactions', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('product_variant_id');
        $table->string('type', 50);
        $table->integer('quantity');
        $table->text('note')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
    });
});

test('reservation migration creates its schema and reference uniqueness', function () {
    $migration = require database_path('migrations/2026_07_13_000001_create_inventory_reservations_table.php');
    $migration->up();

    expect(Schema::connection('bstore_catalog')->hasTable('inventory_reservations'))->toBeTrue()
        ->and(Schema::connection('bstore_catalog')->hasColumn('inventory_transactions', 'reference'))->toBeTrue();

    DB::connection('bstore_catalog')->table('inventory_reservations')->insert([
        'reference' => 'ORDER-MIGRATION',
        'product_variant_id' => 1,
        'quantity' => 1,
        'status' => 'reserved',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::connection('bstore_catalog')->table('inventory_reservations')->insert([
        'reference' => 'ORDER-MIGRATION',
        'product_variant_id' => 1,
        'quantity' => 2,
        'status' => 'reserved',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
