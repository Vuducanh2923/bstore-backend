<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'bstore_order';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable('orders')) {
            return;
        }

        $schema->table('orders', function (Blueprint $table) use ($schema): void {
            if (! $schema->hasColumn('orders', 'cancel_request_status')) {
                $table->string('cancel_request_status', 20)->default('none')->index()->after('status');
            }
            if (! $schema->hasColumn('orders', 'refund_status')) {
                $table->string('refund_status', 20)->default('none')->index()->after('cancel_request_status');
            }
            if (! $schema->hasColumn('orders', 'return_status')) {
                $table->string('return_status', 20)->default('none')->index()->after('refund_status');
            }
            if (! $schema->hasColumn('orders', 'return_reason')) {
                $table->text('return_reason')->nullable()->after('cancel_reason');
            }
        });

        DB::connection(self::CONNECTION)->table('orders')
            ->whereIn('status', ['confirmed', 'packing'])->update(['status' => 'processing']);
        DB::connection(self::CONNECTION)->table('orders')->where('status', 'pending_cancel')->update([
            'status' => DB::raw("CASE WHEN assigned_staff_id IS NULL THEN 'pending' ELSE 'processing' END"),
            'cancel_request_status' => 'pending',
        ]);
        DB::connection(self::CONNECTION)->table('orders')->where('status', 'refunded')->update([
            'status' => 'cancelled',
            'refund_status' => 'completed',
        ]);
        DB::connection(self::CONNECTION)->table('orders')->where('status', 'returned')->update([
            'status' => 'completed',
            'return_status' => 'completed',
        ]);
        DB::connection(self::CONNECTION)->table('orders')->where('status', 'failed')->update(['status' => 'cancelled']);

        if ($schema->hasTable('refund_requests')) {
            $mappings = [
                'pending' => 'pending',
                'approved' => 'pending',
                'refunding' => 'processing',
                'refunded' => 'completed',
                'rejected' => 'failed',
            ];

            foreach ($mappings as $legacyStatus => $refundStatus) {
                $orderIds = DB::connection(self::CONNECTION)->table('refund_requests')
                    ->where('status', $legacyStatus)
                    ->pluck('order_id');

                if ($orderIds->isNotEmpty()) {
                    DB::connection(self::CONNECTION)->table('orders')
                        ->whereIn('id', $orderIds->all())
                        ->update(['refund_status' => $refundStatus]);
                }
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable('orders')) {
            return;
        }

        DB::connection(self::CONNECTION)->table('orders')
            ->where('cancel_request_status', 'pending')
            ->update(['status' => 'pending_cancel']);
        DB::connection(self::CONNECTION)->table('orders')
            ->where('refund_status', 'completed')
            ->where('status', 'cancelled')
            ->update(['status' => 'refunded']);
        DB::connection(self::CONNECTION)->table('orders')
            ->where('return_status', 'completed')
            ->update(['status' => 'returned']);

        $schema->table('orders', function (Blueprint $table) use ($schema): void {
            foreach (['return_reason', 'return_status', 'refund_status', 'cancel_request_status'] as $column) {
                if ($schema->hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
