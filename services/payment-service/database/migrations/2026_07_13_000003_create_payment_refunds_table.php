<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('bstore_payment')->create('payment_refunds', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('request_id', 64)->unique();
            $table->string('provider_refund_id', 191)->nullable()->index();
            $table->decimal('amount', 15, 2);
            $table->string('transaction_type', 2);
            $table->string('status', 20)->default('pending')->index();
            $table->string('response_code', 10)->nullable();
            $table->string('transaction_status', 10)->nullable();
            $table->string('reason', 255);
            $table->string('requested_by', 100);
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('bstore_payment')->dropIfExists('payment_refunds');
    }
};
