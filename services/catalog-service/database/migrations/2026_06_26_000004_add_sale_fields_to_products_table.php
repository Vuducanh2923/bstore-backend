<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('bstore_catalog')->hasTable('products')) {
            return;
        }

        Schema::connection('bstore_catalog')->table('products', function (Blueprint $table) {
            if (! Schema::connection('bstore_catalog')->hasColumn('products', 'sale_percent')) {
                $table->decimal('sale_percent', 5, 2)->nullable()->after('price');
            }

            if (! Schema::connection('bstore_catalog')->hasColumn('products', 'sale_price')) {
                $table->decimal('sale_price', 15, 2)->nullable()->after('sale_percent');
            }

            if (! Schema::connection('bstore_catalog')->hasColumn('products', 'is_sale')) {
                $table->boolean('is_sale')->default(false)->after('sale_price');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('bstore_catalog')->hasTable('products')) {
            return;
        }

        Schema::connection('bstore_catalog')->table('products', function (Blueprint $table) {
            foreach (['is_sale', 'sale_price', 'sale_percent'] as $column) {
                if (Schema::connection('bstore_catalog')->hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
