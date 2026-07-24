<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('paid_at');
            }
        });

        Schema::table('warranty_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('warranty_requests', 'request_code')) {
                $table->string('request_code', 191)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('warranty_requests', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->index()->after('order_item_id');
            }
            if (! Schema::hasColumn('warranty_requests', 'description')) {
                $table->text('description')->nullable()->after('reason');
            }
            if (! Schema::hasColumn('warranty_requests', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }
            if (! Schema::hasColumn('warranty_requests', 'processing_note')) {
                $table->text('processing_note')->nullable()->after('rejection_reason');
            }
            if (! Schema::hasColumn('warranty_requests', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->index()->after('processing_note');
            }
            if (! Schema::hasColumn('warranty_requests', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('warranty_requests', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->index()->after('approved_at');
            }
            if (! Schema::hasColumn('warranty_requests', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('warranty_requests', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('rejected_at');
            }
            if (! Schema::hasColumn('warranty_requests', 'warranty_start_date')) {
                $table->date('warranty_start_date')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('warranty_requests', 'warranty_end_date')) {
                $table->date('warranty_end_date')->nullable()->after('warranty_start_date');
            }
            if (! Schema::hasColumn('warranty_requests', 'created_at')) {
                $table->timestamp('created_at')->nullable()->index()->after('warranty_end_date');
            }
            if (! Schema::hasColumn('warranty_requests', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warranty_requests', function (Blueprint $table): void {
            $columns = [
                'request_code', 'product_id', 'description', 'rejection_reason',
                'processing_note', 'approved_by', 'approved_at', 'rejected_by',
                'rejected_at', 'completed_at', 'warranty_start_date',
                'warranty_end_date', 'created_at', 'updated_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('warranty_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
        });
    }
};
