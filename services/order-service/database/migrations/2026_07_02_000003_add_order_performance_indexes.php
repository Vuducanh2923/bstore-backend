<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'bstore_order';

    private const INDEXES = [
        'carts' => [
            'idx_order_carts_user_status' => ['user_id', 'status'],
        ],
        'cart_items' => [
            'idx_order_cart_items_cart_variant' => ['cart_id', 'product_variant_id'],
        ],
        'orders' => [
            'idx_order_orders_status' => ['status'],
            'idx_order_orders_payment_status' => ['payment_status'],
            'idx_order_orders_created_at' => ['created_at'],
            'idx_order_orders_user_created' => ['user_id', 'created_at'],
            'idx_order_orders_payment_method' => ['payment_method'],
        ],
        'order_items' => [
            'idx_order_order_items_order_product' => ['order_id', 'product_id'],
        ],
        'discounts' => [
            'idx_order_discounts_status' => ['status'],
        ],
        'warranty_requests' => [
            'idx_order_warranty_requests_status' => ['status'],
            'idx_order_warranty_requests_user_status' => ['user_id', 'status'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $index => $columns) {
                $this->addIndex($table, $columns, $index);
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach (array_keys($indexes) as $index) {
                $this->dropIndex($table, $index);
            }
        }
    }

    private function addIndex(string $table, array $columns, string $index): void
    {
        if (! Schema::connection(self::CONNECTION)->hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::connection(self::CONNECTION)->hasColumn($table, $column)) {
                return;
            }
        }

        DB::connection(self::CONNECTION)->statement(sprintf(
            'CREATE INDEX %s ON %s (%s)',
            $index,
            $table,
            implode(', ', $columns),
        ));
    }

    private function dropIndex(string $table, string $index): void
    {
        if (! Schema::connection(self::CONNECTION)->hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }

        if (DB::connection(self::CONNECTION)->getDriverName() === 'sqlite') {
            DB::connection(self::CONNECTION)->statement("DROP INDEX {$index}");

            return;
        }

        DB::connection(self::CONNECTION)->statement("ALTER TABLE {$table} DROP INDEX {$index}");
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = DB::connection(self::CONNECTION);

        if ($connection->getDriverName() === 'sqlite') {
            return collect($connection->select("PRAGMA index_list('{$table}')"))
                ->contains(fn (object $row): bool => ($row->name ?? null) === $index);
        }

        return $connection->table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $connection->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
