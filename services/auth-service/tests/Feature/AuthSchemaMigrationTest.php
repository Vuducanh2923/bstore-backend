<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('fresh auth schema enforces unique users and cascading address ownership', function () {
    config([
        'database.default' => 'bstore_auth',
        'database.connections.bstore_auth' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    DB::purge('bstore_auth');

    $migration = require database_path('migrations/2026_06_13_000001_create_auth_tables.php');
    $migration->up();

    expect(Schema::connection('bstore_auth')->hasTable('roles'))->toBeTrue()
        ->and(Schema::connection('bstore_auth')->hasTable('users'))->toBeTrue()
        ->and(Schema::connection('bstore_auth')->hasTable('user_addresses'))->toBeTrue();

    DB::connection('bstore_auth')->table('roles')->insert([
        'id' => 1,
        'name' => 'CUSTOMER',
    ]);

    DB::connection('bstore_auth')->table('users')->insert([
        'id' => 1,
        'role_id' => 1,
        'full_name' => 'First User',
        'email' => 'first@example.com',
        'phone' => '0900000001',
        'password' => 'hash',
    ]);

    expect(fn () => DB::connection('bstore_auth')->table('users')->insert([
        'id' => 2,
        'role_id' => 1,
        'full_name' => 'Duplicate Phone',
        'email' => 'second@example.com',
        'phone' => '0900000001',
        'password' => 'hash',
    ]))->toThrow(QueryException::class);

    DB::connection('bstore_auth')->table('user_addresses')->insert([
        'user_id' => 1,
        'receiver_name' => 'First User',
        'receiver_phone' => '0900000001',
        'address' => 'Test address',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::connection('bstore_auth')->table('users')->where('id', 1)->delete();

    expect(DB::connection('bstore_auth')->table('user_addresses')->count())->toBe(0);

    $migration->down();
});
