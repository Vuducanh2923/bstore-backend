<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    // Áp dụng thay đổi cấu trúc cơ sở dữ liệu.
    public function up(): void
    {
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'issued_at')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->timestamp('issued_at')->nullable();
            });
        }
    }

    // Hoàn tác thay đổi cấu trúc cơ sở dữ liệu.
    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'issued_at')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('issued_at');
            });
        }
    }
};
