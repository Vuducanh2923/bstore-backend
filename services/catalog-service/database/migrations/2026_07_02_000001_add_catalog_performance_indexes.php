<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'bstore_catalog';

    private const INDEXES = [
        'categories' => [
            'idx_catalog_categories_status' => ['status'],
        ],
        'brands' => [
            'idx_catalog_brands_status' => ['status'],
        ],
        'banners' => [
            'idx_catalog_banners_status_sort' => ['status', 'sort_order'],
            'idx_catalog_banners_display_slot' => ['display_slot'],
        ],
        'products' => [
            'idx_catalog_products_status_created' => ['status', 'created_at'],
            'idx_catalog_products_category_status' => ['category_id', 'status'],
            'idx_catalog_products_brand_status' => ['brand_id', 'status'],
        ],
        'product_variants' => [
            'idx_catalog_product_variants_status' => ['status'],
            'idx_catalog_product_variants_product_status' => ['product_id', 'status'],
        ],
        'product_images' => [
            'idx_catalog_product_images_product_thumbnail' => ['product_id', 'is_thumbnail'],
            'idx_catalog_product_images_variant_thumbnail' => ['product_variant_id', 'is_thumbnail'],
        ],
        'inventory_transactions' => [
            'idx_catalog_inventory_transactions_variant_type' => ['product_variant_id', 'type'],
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
