<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->nullable()->default('active');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->nullable()->default('active');
        });

        Schema::create('warranty_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('duration_months')->default(0);
            $table->unsignedInteger('return_days')->default(0);
            $table->unsignedInteger('exchange_days')->default(0);
            $table->boolean('repair_support')->default(false);
            $table->text('description')->nullable();
            $table->string('status', 20)->nullable()->default('active');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->index();
            $table->unsignedBigInteger('brand_id')->index();
            $table->unsignedBigInteger('warranty_policy_id')->nullable()->index();
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->longText('description')->nullable();
            $table->json('specifications')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->string('status', 20)->nullable()->default('active');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('color', 50)->nullable();
            $table->string('ram', 50)->nullable();
            $table->string('storage', 50)->nullable();
            $table->json('specifications')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->string('sku', 191)->unique();
            $table->string('barcode', 191)->nullable();
            $table->string('status', 20)->nullable()->default('active');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('product_variant_id')->nullable()->index();
            $table->string('image_url', 500);
            $table->string('public_id')->nullable();
            $table->boolean('is_thumbnail')->default(false);
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_variant_id')->unique();
            $table->integer('quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_variant_id')->index();
            $table->string('type', 50);
            $table->integer('quantity');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('warranty_policies');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
