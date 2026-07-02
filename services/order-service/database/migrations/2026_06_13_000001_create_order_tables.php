<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status', 20)->nullable()->default('active');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id')->index();
            $table->unsignedBigInteger('product_variant_id')->index();
            $table->string('product_name');
            $table->string('color', 50)->nullable();
            $table->string('ram', 50)->nullable();
            $table->string('storage', 50)->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('order_code', 191)->nullable()->unique();
            $table->string('receiver_name');
            $table->string('receiver_phone', 20);
            $table->string('receiver_email', 191)->nullable();
            $table->text('shipping_address');
            $table->string('shipping_method', 50);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2)->default(0);
            $table->string('status', 20)->nullable()->default('pending');
            $table->string('payment_status', 20)->nullable()->default('unpaid');
            $table->text('cancel_reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('product_variant_id')->index();
            $table->string('product_name');
            $table->string('color', 50)->nullable();
            $table->string('ram', 50)->nullable();
            $table->string('storage', 50)->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 191)->unique();
            $table->string('name');
            $table->string('type', 50);
            $table->decimal('value', 15, 2)->default(0);
            $table->decimal('min_order_amount', 15, 2)->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->string('status', 20)->nullable()->default('active');
        });

        Schema::create('order_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('discount_id')->index();
            $table->string('discount_code', 191);
            $table->decimal('discount_amount', 15, 2)->default(0);
        });

        Schema::create('warranty_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('order_item_id')->index();
            $table->string('type', 50);
            $table->text('reason')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('status', 20)->nullable()->default('pending');
            $table->text('admin_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_requests');
        Schema::dropIfExists('order_discounts');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
