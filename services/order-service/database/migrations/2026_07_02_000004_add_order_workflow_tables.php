<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    // Áp dụng thay đổi cấu trúc cơ sở dữ liệu.
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'assigned_staff_id')) {
                    $table->unsignedBigInteger('assigned_staff_id')->nullable()->index()->after('payment_status');
                }

                if (! Schema::hasColumn('orders', 'assigned_staff_name')) {
                    $table->string('assigned_staff_name', 191)->nullable()->after('assigned_staff_id');
                }

                if (! Schema::hasColumn('orders', 'assigned_at')) {
                    $table->timestamp('assigned_at')->nullable()->after('assigned_staff_name');
                }

                if (! Schema::hasColumn('orders', 'processing_note')) {
                    $table->text('processing_note')->nullable()->after('assigned_at');
                }
            });
        }

        if (! Schema::hasTable('refund_requests')) {
            Schema::create('refund_requests', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->text('reason');
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('status', 20)->default('pending')->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->string('refund_method', 50)->nullable();
                $table->string('refund_transaction', 191)->nullable();
                $table->text('admin_note')->nullable();
                $table->timestamps();
                $table->unique('order_id');
                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('complaints')) {
            Schema::create('complaints', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('staff_id')->nullable()->index();
                $table->string('staff_name', 191)->nullable();
                $table->string('staff_phone', 30)->nullable();
                $table->string('title', 191);
                $table->text('content');
                $table->string('status', 20)->default('pending')->index();
                $table->text('reply')->nullable();
                $table->timestamp('handled_at')->nullable();
                $table->timestamps();
                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('order_histories')) {
            Schema::create('order_histories', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->string('action', 50)->index();
                $table->string('old_status', 20)->nullable();
                $table->string('new_status', 20)->nullable();
                $table->unsignedBigInteger('staff_id')->nullable()->index();
                $table->string('staff_name', 191)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('type', 50)->index();
                $table->string('title', 191)->nullable();
                $table->text('message');
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            });
        }
    }

    // Hoàn tác thay đổi cấu trúc cơ sở dữ liệu.
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('order_histories');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('refund_requests');

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['processing_note', 'assigned_at', 'assigned_staff_name', 'assigned_staff_id'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
