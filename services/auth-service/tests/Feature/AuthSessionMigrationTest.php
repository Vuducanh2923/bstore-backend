<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('auth session migration creates unique token indexes and a cascading user foreign key', function () {
    config([
        'database.connections.bstore_auth' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    DB::purge('bstore_auth');

    Schema::connection('bstore_auth')->create('users', function (Blueprint $table) {
        $table->id();
    });

    $migration = require database_path('migrations/2026_07_13_000004_create_auth_sessions_table.php');
    $migration->up();

    expect(Schema::connection('bstore_auth')->hasColumns('auth_sessions', [
        'id',
        'user_id',
        'access_jti',
        'refresh_token_hash',
        'refresh_expires_at',
        'revoked_at',
    ]))->toBeTrue();

    $indexes = collect(DB::connection('bstore_auth')->select('PRAGMA index_list(auth_sessions)'));
    $foreignKeys = collect(DB::connection('bstore_auth')->select('PRAGMA foreign_key_list(auth_sessions)'));

    expect($indexes->where('unique', 1)->pluck('name')->all())
        ->toContain('auth_sessions_access_jti_unique', 'auth_sessions_refresh_token_hash_unique')
        ->and($foreignKeys->contains(fn (object $key) => $key->table === 'users'
            && $key->from === 'user_id'
            && strtoupper((string) $key->on_delete) === 'CASCADE'))
        ->toBeTrue();

    $migration->down();

    expect(Schema::connection('bstore_auth')->hasTable('auth_sessions'))->toBeFalse();
});
