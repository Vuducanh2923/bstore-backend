<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'bstore_catalog';

    // Áp dụng thay đổi cấu trúc cơ sở dữ liệu.
    public function up(): void
    {
        $db = DB::connection(self::CONNECTION);
        $schema = Schema::connection(self::CONNECTION);

        if ($db->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['brands', 'categories', 'warranty_policies', 'products', 'product_variants', 'product_images', 'inventories', 'inventory_transactions', 'banners', 'inventory_reservations'] as $table) {
            if ($schema->hasTable($table)) {
                $db->statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
            }
        }

        if ($schema->hasTable('products')) {
            $db->statement('UPDATE `products` p LEFT JOIN `warranty_policies` w ON w.`id` = p.`warranty_policy_id` SET p.`warranty_policy_id` = NULL WHERE p.`warranty_policy_id` IS NOT NULL AND w.`id` IS NULL');
            $db->statement('DELETE i FROM `product_images` i LEFT JOIN `products` p ON p.`id` = i.`product_id` WHERE p.`id` IS NULL');
            $db->statement('DELETE t FROM `inventory_transactions` t LEFT JOIN `product_variants` v ON v.`id` = t.`product_variant_id` WHERE v.`id` IS NULL');
            if ($schema->hasTable('inventory_reservations')) {
                $db->statement('DELETE r FROM `inventory_reservations` r LEFT JOIN `product_variants` v ON v.`id` = r.`product_variant_id` WHERE v.`id` IS NULL');
            }
            $db->statement('DELETE i FROM `inventories` i LEFT JOIN `product_variants` v ON v.`id` = i.`product_variant_id` WHERE v.`id` IS NULL');
            $db->statement('UPDATE `product_images` i LEFT JOIN `product_variants` v ON v.`id` = i.`product_variant_id` SET i.`product_variant_id` = NULL WHERE i.`product_variant_id` IS NOT NULL AND v.`id` IS NULL');
            $db->statement('DELETE v FROM `product_variants` v LEFT JOIN `products` p ON p.`id` = v.`product_id` WHERE p.`id` IS NULL');
            $db->statement('DELETE p FROM `products` p LEFT JOIN `categories` c ON c.`id` = p.`category_id` LEFT JOIN `brands` b ON b.`id` = p.`brand_id` WHERE c.`id` IS NULL OR b.`id` IS NULL');
            $db->statement('DELETE i FROM `product_images` i LEFT JOIN `products` p ON p.`id` = i.`product_id` WHERE p.`id` IS NULL');
            $db->statement('DELETE t FROM `inventory_transactions` t JOIN `product_variants` v ON v.`id` = t.`product_variant_id` LEFT JOIN `products` p ON p.`id` = v.`product_id` WHERE p.`id` IS NULL');
            if ($schema->hasTable('inventory_reservations')) {
                $db->statement('DELETE r FROM `inventory_reservations` r JOIN `product_variants` v ON v.`id` = r.`product_variant_id` LEFT JOIN `products` p ON p.`id` = v.`product_id` WHERE p.`id` IS NULL');
            }
            $db->statement('DELETE i FROM `inventories` i JOIN `product_variants` v ON v.`id` = i.`product_variant_id` LEFT JOIN `products` p ON p.`id` = v.`product_id` WHERE p.`id` IS NULL');
            $db->statement('DELETE i FROM `product_images` i JOIN `product_variants` v ON v.`id` = i.`product_variant_id` LEFT JOIN `products` p ON p.`id` = v.`product_id` WHERE p.`id` IS NULL');
            $db->statement('DELETE v FROM `product_variants` v LEFT JOIN `products` p ON p.`id` = v.`product_id` WHERE p.`id` IS NULL');
        }

        $this->addForeign('products', 'category_id', 'categories', 'restrict');
        $this->addForeign('products', 'brand_id', 'brands', 'restrict');
        $this->addForeign('products', 'warranty_policy_id', 'warranty_policies', 'set null');
        $this->addForeign('product_variants', 'product_id', 'products', 'cascade');
        $this->addForeign('product_images', 'product_id', 'products', 'cascade');
        $this->addForeign('product_images', 'product_variant_id', 'product_variants', 'set null');
        $this->addForeign('inventories', 'product_variant_id', 'product_variants', 'cascade');
        $this->addForeign('inventory_transactions', 'product_variant_id', 'product_variants', 'restrict');
        $this->addForeign('inventory_reservations', 'product_variant_id', 'product_variants', 'restrict');
    }

    // Hoàn tác thay đổi cấu trúc cơ sở dữ liệu.
    public function down(): void {}

    // Tạo hoặc lưu khóa ngoại.
    private function addForeign(string $table, string $column, string $parent, string $onDelete): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $name = "{$table}_{$column}_foreign";

        if (! $schema->hasTable($table) || ! $schema->hasTable($parent) || ! $schema->hasColumn($table, $column) || $this->foreignExists($table, $name)) {
            return;
        }

        $schema->table($table, fn (Blueprint $blueprint) => $blueprint->foreign($column, $name)->references('id')->on($parent)->onDelete($onDelete));
    }

    // Thực hiện khóa ngoại tồn tại.
    private function foreignExists(string $table, string $name): bool
    {
        $db = DB::connection(self::CONNECTION);

        return $db->table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $db->getDatabaseName())->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)->where('CONSTRAINT_TYPE', 'FOREIGN KEY')->exists();
    }
};
