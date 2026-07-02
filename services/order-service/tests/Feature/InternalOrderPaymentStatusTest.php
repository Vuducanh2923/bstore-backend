<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'database.default' => 'bstore_order',
        'database.connections.bstore_order' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    DB::purge('bstore_order');

    Schema::connection('bstore_order')->create('orders', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->index();
        $table->string('status', 20)->nullable()->default('pending');
        $table->string('payment_status', 20)->nullable()->default('unpaid');
        $table->string('payment_method', 50)->nullable();
        $table->timestamp('paid_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });
});

test('internal payment status endpoint marks an order paid and confirmed', function () {
    $orderId = DB::connection('bstore_order')->table('orders')->insertGetId([
        'user_id' => 10,
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    $this->patchJson("/api/internal/orders/{$orderId}/payment-status", [
        'payment_status' => 'paid',
        'payment_method' => 'vnpay',
        'paid_at' => '2026-07-02 12:30:00',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.order_id', $orderId)
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payment_method', 'vnpay');

    $order = DB::connection('bstore_order')->table('orders')->where('id', $orderId)->first();

    expect($order->status)->toBe('confirmed')
        ->and($order->payment_status)->toBe('paid')
        ->and($order->payment_method)->toBe('vnpay')
        ->and($order->paid_at)->not->toBeNull();
});

test('internal payment status endpoint returns not found for missing order', function () {
    $this->patchJson('/api/internal/orders/999/payment-status', [
        'payment_status' => 'paid',
        'payment_method' => 'vnpay',
        'paid_at' => '2026-07-02 12:30:00',
    ])
        ->assertNotFound()
        ->assertJsonPath('success', false);
});
