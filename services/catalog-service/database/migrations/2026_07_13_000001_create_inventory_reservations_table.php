<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'bstore_catalog';

    // Áp dụng thay đổi cấu trúc cơ sở dữ liệu.
    public function up(): void
    {
        if (! Schema::connection(self::CONNECTION)->hasTable('inventory_reservations')) {
            Schema::connection(self::CONNECTION)->create('inventory_reservations', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('reference', 191);
                $table->unsignedBigInteger('product_variant_id');
                $table->unsignedInteger('quantity');
                $table->string('status', 20)->default('reserved');
                $table->timestamps();

                $table->unique(['reference', 'product_variant_id'], 'inventory_reservations_reference_variant_unique');
                $table->index(['reference', 'status'], 'inventory_reservations_reference_status_index');
                $table->index(['product_variant_id', 'status'], 'inventory_reservations_variant_status_index');
            });
        }

        if (
            Schema::connection(self::CONNECTION)->hasTable('inventory_transactions')
            && ! Schema::connection(self::CONNECTION)->hasColumn('inventory_transactions', 'reference')
        ) {
            Schema::connection(self::CONNECTION)->table('inventory_transactions', function (Blueprint $table): void {
                $table->string('reference', 191)->nullable()->after('quantity')->index();
            });
        }
    }

    // Hoàn tác thay đổi cấu trúc cơ sở dữ liệu.
    public function down(): void
    {
        if (
            Schema::connection(self::CONNECTION)->hasTable('inventory_transactions')
            && Schema::connection(self::CONNECTION)->hasColumn('inventory_transactions', 'reference')
        ) {
            Schema::connection(self::CONNECTION)->table('inventory_transactions', function (Blueprint $table): void {
                $table->dropColumn('reference');
            });
        }

        Schema::connection(self::CONNECTION)->dropIfExists('inventory_reservations');
    }
};
