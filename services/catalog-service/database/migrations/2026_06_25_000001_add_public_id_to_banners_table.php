<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    // Áp dụng thay đổi cấu trúc cơ sở dữ liệu.
    public function up(): void
    {
        if (! Schema::hasTable('banners') || Schema::hasColumn('banners', 'public_id')) {
            return;
        }

        Schema::table('banners', function (Blueprint $table) {
            $table->string('public_id')->nullable()->after('image_url');
        });
    }

    // Hoàn tác thay đổi cấu trúc cơ sở dữ liệu.
    public function down(): void
    {
        if (! Schema::hasTable('banners') || ! Schema::hasColumn('banners', 'public_id')) {
            return;
        }

        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });
    }
};
