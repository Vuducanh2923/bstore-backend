<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('banners')) {
            if (! Schema::hasColumn('banners', 'route')) {
                Schema::table('banners', function (Blueprint $table) {
                    $table->string('route', 255)->nullable();
                });
            }

            return;
        }

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('button_text')->nullable();
            $table->string('button_link')->nullable();
            $table->string('image_url', 500);
            $table->string('public_id')->nullable();
            $table->string('route', 255)->nullable();
            $table->unsignedTinyInteger('display_slot')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['status', 'display_slot', 'sort_order']);
            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
