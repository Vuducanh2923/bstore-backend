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
        $table->decimal('final_amount', 15, 2)->default(0);
        $table->timestamp('paid_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });
});

test('internal payment status endpoint marks an order paid without assigning staff', function () {
    $orderId = DB::connection('bstore_order')->table('orders')->insertGetId([
        'user_id' => 10,
        'status' => 'pending',
        'payment_status' => 'pending',
        'payment_method' => 'vnpay',
        'final_amount' => 125000,
    ]);

    $this->withHeaders(internalServiceHeaders())->patchJson("/api/internal/orders/{$orderId}/payment-status", [
        'payment_status' => 'paid',
        'paid_at' => '2026-07-02 12:30:00',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.order_id', $orderId)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payment_method', 'vnpay');

    $order = DB::connection('bstore_order')->table('orders')->where('id', $orderId)->first();

    expect($order->status)->toBe('pending')
        ->and($order->payment_status)->toBe('paid')
        ->and($order->payment_method)->toBe('vnpay')
        ->and($order->paid_at)->not->toBeNull();
});

test('internal payment status endpoint returns not found for missing order', function () {
    $this->withHeaders(internalServiceHeaders())->patchJson('/api/internal/orders/999/payment-status', [
        'payment_status' => 'paid',
        'paid_at' => '2026-07-02 12:30:00',
    ])
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

test('internal payment context enforces optional customer ownership', function () {
    $orderId = DB::connection('bstore_order')->table('orders')->insertGetId([
        'user_id' => 10,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'vnpay',
        'final_amount' => 125000,
    ]);

    $this->withHeaders(internalServiceHeaders())
        ->getJson("/api/internal/orders/{$orderId}/payment-context?customer_id=10")
        ->assertOk()
        ->assertJsonPath('data.customer_id', 10)
        ->assertJsonPath('data.final_amount', 125000)
        ->assertJsonPath('data.payment_method', 'vnpay')
        ->assertJsonPath('data.order_status', 'pending');

    $this->withHeaders(internalServiceHeaders())
        ->getJson("/api/internal/orders/{$orderId}/payment-context?customer_id=11")
        ->assertNotFound();
});

test('internal endpoints deny requests without shared token', function () {
    expect(config('services.internal.token'))->toBe('test-internal-token');
    $this->getJson('/api/internal/orders/1/payment-context')->assertUnauthorized();
    $this->patchJson('/api/internal/orders/1/payment-status', [
        'payment_status' => 'paid',
    ])->assertUnauthorized();
});
