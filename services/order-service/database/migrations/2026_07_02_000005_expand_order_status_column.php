<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'bstore_order';

    public function up(): void
    {
        if (
            ! Schema::connection(self::CONNECTION)->hasTable('orders')
            || ! Schema::connection(self::CONNECTION)->hasColumn('orders', 'status')
        ) {
            return;
        }

        $connection = DB::connection(self::CONNECTION);

        if ($connection->getDriverName() === 'mysql') {
            $connection->statement("ALTER TABLE orders MODIFY status VARCHAR(30) NULL DEFAULT 'pending'");

            return;
        }

        if ($connection->getDriverName() !== 'sqlite') {
            Schema::connection(self::CONNECTION)->table('orders', function ($table) {
                $table->string('status', 30)->nullable()->default('pending')->change();
            });
        }
    }

    public function down(): void
    {
        if (
            ! Schema::connection(self::CONNECTION)->hasTable('orders')
            || ! Schema::connection(self::CONNECTION)->hasColumn('orders', 'status')
        ) {
            return;
        }

        $connection = DB::connection(self::CONNECTION);

        if ($connection->getDriverName() === 'mysql') {
            $connection->statement("ALTER TABLE orders MODIFY status VARCHAR(20) NULL DEFAULT 'pending'");

            return;
        }

        if ($connection->getDriverName() !== 'sqlite') {
            Schema::connection(self::CONNECTION)->table('orders', function ($table) {
                $table->string('status', 20)->nullable()->default('pending')->change();
            });
        }
    }
};
