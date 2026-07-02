<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'database.connections.bstore_payment' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    DB::purge('bstore_payment');

    Schema::connection('bstore_payment')->create('payments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id')->index();
        $table->string('payment_method', 50);
        $table->string('payment_provider', 50)->nullable();
        $table->string('transaction_code', 191)->nullable();
        $table->decimal('amount', 15, 2)->default(0);
        $table->string('status', 20)->nullable()->default('pending');
        $table->dateTime('paid_at')->nullable();
    });

    Schema::connection('bstore_payment')->create('invoices', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('payment_id')->index();
        $table->unsignedBigInteger('order_id')->index();
        $table->string('invoice_code', 191)->unique();
        $table->decimal('total_amount', 15, 2)->default(0);
        $table->dateTime('issued_at')->nullable();
    });
});

test('internal payment and invoice endpoints return records by order id', function () {
    $paymentId = DB::connection('bstore_payment')->table('payments')->insertGetId([
        'order_id' => 123,
        'payment_method' => 'bank_transfer',
        'payment_provider' => 'vnpay',
        'transaction_code' => 'TX123',
        'amount' => 90000,
        'status' => 'paid',
        'paid_at' => '2026-07-01 10:00:00',
    ]);

    DB::connection('bstore_payment')->table('invoices')->insert([
        'payment_id' => $paymentId,
        'order_id' => 123,
        'invoice_code' => 'INV123',
        'total_amount' => 90000,
        'issued_at' => '2026-07-01 10:05:00',
    ]);

    $this->getJson('/api/internal/orders/123/payment')
        ->assertOk()
        ->assertJsonPath('data.payment_method', 'bank_transfer')
        ->assertJsonPath('data.transaction_code', 'TX123')
        ->assertJsonPath('data.status', 'paid');

    $this->getJson('/api/internal/orders/123/invoice')
        ->assertOk()
        ->assertJsonPath('data.invoice_code', 'INV123')
        ->assertJsonPath('data.total_amount', '90000.00');
});

test('internal payment endpoints return not found when order has no records', function () {
    $this->getJson('/api/internal/orders/404/payment')->assertNotFound();
    $this->getJson('/api/internal/orders/404/invoice')->assertNotFound();
});
