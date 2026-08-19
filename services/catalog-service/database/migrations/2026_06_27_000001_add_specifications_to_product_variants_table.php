<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    // Áp dụng thay đổi cấu trúc cơ sở dữ liệu.
    public function up(): void
    {
        if (! Schema::connection('bstore_catalog')->hasColumn('product_variants', 'specifications')) {
            Schema::connection('bstore_catalog')->table('product_variants', function (Blueprint $table) {
                $table->json('specifications')->nullable()->after('storage');
            });
        }
    }

    // Hoàn tác thay đổi cấu trúc cơ sở dữ liệu.
    public function down(): void
    {
        if (Schema::connection('bstore_catalog')->hasColumn('product_variants', 'specifications')) {
            Schema::connection('bstore_catalog')->table('product_variants', function (Blueprint $table) {
                $table->dropColumn('specifications');
            });
        }
    }
};
