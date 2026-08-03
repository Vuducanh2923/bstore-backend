<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('legacy order statuses are migrated into separate business statuses without losing orders', function () {
    config([
        'database.connections.bstore_order' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);
    DB::purge('bstore_order');

    Schema::connection('bstore_order')->create('orders', function (Blueprint $table): void {
        $table->id();
        $table->string('status', 30)->default('pending');
        $table->unsignedBigInteger('assigned_staff_id')->nullable();
        $table->text('cancel_reason')->nullable();
    });
    Schema::connection('bstore_order')->create('refund_requests', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->string('status', 20);
    });

    DB::connection('bstore_order')->table('orders')->insert([
        ['id' => 1, 'status' => 'confirmed', 'assigned_staff_id' => 2],
        ['id' => 2, 'status' => 'pending_cancel', 'assigned_staff_id' => null],
        ['id' => 3, 'status' => 'pending_cancel', 'assigned_staff_id' => 2],
        ['id' => 4, 'status' => 'refunded', 'assigned_staff_id' => 2],
        ['id' => 5, 'status' => 'returned', 'assigned_staff_id' => 2],
    ]);
    DB::connection('bstore_order')->table('refund_requests')->insert([
        ['order_id' => 3, 'status' => 'refunding'],
        ['order_id' => 4, 'status' => 'refunded'],
    ]);

    $migration = require database_path('migrations/2026_08_03_000001_separate_order_business_statuses.php');
    $migration->up();

    expect(DB::connection('bstore_order')->table('orders')->count())->toBe(5)
        ->and(DB::connection('bstore_order')->table('orders')->find(1)->status)->toBe('processing')
        ->and(DB::connection('bstore_order')->table('orders')->find(2)->status)->toBe('pending')
        ->and(DB::connection('bstore_order')->table('orders')->find(2)->cancel_request_status)->toBe('pending')
        ->and(DB::connection('bstore_order')->table('orders')->find(3)->status)->toBe('processing')
        ->and(DB::connection('bstore_order')->table('orders')->find(3)->refund_status)->toBe('processing')
        ->and(DB::connection('bstore_order')->table('orders')->find(4)->status)->toBe('cancelled')
        ->and(DB::connection('bstore_order')->table('orders')->find(4)->refund_status)->toBe('completed')
        ->and(DB::connection('bstore_order')->table('orders')->find(5)->status)->toBe('completed')
        ->and(DB::connection('bstore_order')->table('orders')->find(5)->return_status)->toBe('completed');
});
