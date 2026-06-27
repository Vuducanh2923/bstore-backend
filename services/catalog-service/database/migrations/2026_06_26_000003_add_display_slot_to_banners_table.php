<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('banners') || Schema::hasColumn('banners', 'display_slot')) {
            return;
        }

        Schema::table('banners', function (Blueprint $table) {
            $table->unsignedTinyInteger('display_slot')->default(1)->after('route');
            $table->index(['status', 'display_slot', 'sort_order'], 'banners_status_display_slot_sort_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('banners') || ! Schema::hasColumn('banners', 'display_slot')) {
            return;
        }

        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex('banners_status_display_slot_sort_index');
            $table->dropColumn('display_slot');
        });
    }
};
