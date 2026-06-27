<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('banners')) {
            return;
        }

        $needsProductImageId = ! Schema::hasColumn('banners', 'product_image_id');
        $needsImageSource = ! Schema::hasColumn('banners', 'image_source');

        if (! $needsProductImageId && ! $needsImageSource) {
            return;
        }

        Schema::table('banners', function (Blueprint $table) use ($needsProductImageId, $needsImageSource) {
            if ($needsProductImageId) {
                $table->unsignedBigInteger('product_image_id')->nullable()->after('image_url');
                $table->index('product_image_id');
            }

            if ($needsImageSource) {
                $table->string('image_source', 20)->default('url')->after('product_image_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('banners')) {
            return;
        }

        Schema::table('banners', function (Blueprint $table) {
            if (Schema::hasColumn('banners', 'product_image_id')) {
                $table->dropColumn('product_image_id');
            }

            if (Schema::hasColumn('banners', 'image_source')) {
                $table->dropColumn('image_source');
            }
        });
    }
};
