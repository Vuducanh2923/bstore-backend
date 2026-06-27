<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'idx_products_slug' => 'slug',
        'idx_products_category_id' => 'category_id',
        'idx_products_brand_id' => 'brand_id',
        'idx_products_status' => 'status',
        'idx_products_price' => 'price',
        'idx_products_created_at' => 'created_at',
    ];

    public function up(): void
    {
        if (! Schema::connection('bstore_catalog')->hasTable('products')) {
            return;
        }

        foreach (self::INDEXES as $indexName => $column) {
            if (
                Schema::connection('bstore_catalog')->hasColumn('products', $column)
                && ! $this->columnHasIndex($column)
            ) {
                DB::connection('bstore_catalog')
                    ->statement("ALTER TABLE products ADD INDEX {$indexName} ({$column})");
            }
        }
    }

    public function down(): void
    {
        if (! Schema::connection('bstore_catalog')->hasTable('products')) {
            return;
        }

        foreach (array_keys(self::INDEXES) as $indexName) {
            if ($this->indexExists($indexName)) {
                DB::connection('bstore_catalog')
                    ->statement("ALTER TABLE products DROP INDEX {$indexName}");
            }
        }
    }

    private function columnHasIndex(string $column): bool
    {
        return DB::connection('bstore_catalog')
            ->table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::connection('bstore_catalog')->getDatabaseName())
            ->where('TABLE_NAME', 'products')
            ->where('COLUMN_NAME', $column)
            ->where('INDEX_NAME', '<>', 'PRIMARY')
            ->exists();
    }

    private function indexExists(string $indexName): bool
    {
        return DB::connection('bstore_catalog')
            ->table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::connection('bstore_catalog')->getDatabaseName())
            ->where('TABLE_NAME', 'products')
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
