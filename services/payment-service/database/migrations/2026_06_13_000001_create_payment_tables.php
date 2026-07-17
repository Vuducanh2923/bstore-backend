<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->string('payment_method', 50);
            $table->string('payment_provider', 50)->nullable();
            $table->string('transaction_code', 191)->nullable()->unique();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status', 20)->nullable()->default('pending');
            $table->dateTime('paid_at')->nullable();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('transaction_code', 191);
            $table->string('provider', 100);
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status', 20);
            $table->json('response_data')->nullable();
            $table->unique(['transaction_code', 'provider']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained('payments')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->unique();
            $table->string('invoice_code', 191)->unique();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payments');
    }
};
