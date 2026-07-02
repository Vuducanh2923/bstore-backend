<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'payment_method')) {
                    $table->string('payment_method', 50)->nullable()->after('shipping_method');
                }

                if (! Schema::hasColumn('orders', 'shipping_fee')) {
                    $table->decimal('shipping_fee', 15, 2)->default(0)->after('discount_amount');
                }

                if (! Schema::hasColumn('orders', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('order_items', 'product_id')) {
                    $table->unsignedBigInteger('product_id')->nullable()->index()->after('order_id');
                }

                if (! Schema::hasColumn('order_items', 'product_image')) {
                    $table->string('product_image', 500)->nullable()->after('product_name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (Schema::hasColumn('order_items', 'product_image')) {
                    $table->dropColumn('product_image');
                }

                if (Schema::hasColumn('order_items', 'product_id')) {
                    $table->dropColumn('product_id');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['updated_at', 'shipping_fee', 'payment_method'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
