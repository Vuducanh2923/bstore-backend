<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    // Áp dụng thay đổi cấu trúc cơ sở dữ liệu.
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('discounts', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (! Schema::hasColumn('discounts', 'max_discount_amount')) {
                $table->decimal('max_discount_amount', 15, 2)->nullable()->after('value');
            }
            if (! Schema::hasColumn('discounts', 'usage_limit_per_customer')) {
                $table->unsignedInteger('usage_limit_per_customer')->nullable()->after('usage_limit');
            }
            if (! Schema::hasColumn('discounts', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->index()->after('status');
            }
            if (! Schema::hasColumn('discounts', 'created_at')) {
                $table->timestamp('created_at')->nullable()->index()->after('created_by');
            }
            if (! Schema::hasColumn('discounts', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
            if (! Schema::hasColumn('discounts', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    // Hoàn tác thay đổi cấu trúc cơ sở dữ liệu.
    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table): void {
            foreach ([
                'description',
                'max_discount_amount',
                'usage_limit_per_customer',
                'created_by',
                'created_at',
                'updated_at',
                'deleted_at',
            ] as $column) {
                if (Schema::hasColumn('discounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
