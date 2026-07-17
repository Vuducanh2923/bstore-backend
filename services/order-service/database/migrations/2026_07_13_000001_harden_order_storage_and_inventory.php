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
        $connection = DB::connection(self::CONNECTION);

        if ($connection->getDriverName() === 'mysql') {
            foreach ([
                'carts',
                'cart_items',
                'orders',
                'order_items',
                'discounts',
                'order_discounts',
                'warranty_requests',
                'refund_requests',
                'complaints',
                'order_histories',
                'notifications',
            ] as $table) {
                if ($schema->hasTable($table)) {
                    $connection->statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
                }
            }
        }

        if ($schema->hasTable('orders')) {
            $schema->table('orders', function (Blueprint $table) use ($schema): void {
                if (! $schema->hasColumn('orders', 'cart_id')) {
                    $table->unsignedBigInteger('cart_id')->nullable()->index()->after('user_id');
                }

                if (! $schema->hasColumn('orders', 'inventory_reference')) {
                    $table->string('inventory_reference', 191)->nullable()->after('payment_status');
                }

                if (! $schema->hasColumn('orders', 'inventory_state')) {
                    $table->string('inventory_state', 20)->nullable()->after('inventory_reference');
                }

                if (! $schema->hasColumn('orders', 'inventory_updated_at')) {
                    $table->timestamp('inventory_updated_at')->nullable()->after('inventory_state');
                }
            });
        }

        $this->cleanupLegacyData();

        $this->addUnique('orders', ['inventory_reference'], 'orders_inventory_reference_unique');
        $this->addUnique('cart_items', ['cart_id', 'product_variant_id'], 'cart_items_cart_id_product_variant_id_unique');
        $this->addUnique('order_items', ['order_id', 'product_variant_id'], 'order_items_order_id_product_variant_id_unique');
        $this->addUnique('order_discounts', ['order_id', 'discount_id'], 'order_discounts_order_id_discount_id_unique');
        $this->addUnique('refund_requests', ['order_id'], 'refund_requests_order_id_unique');

        if ($connection->getDriverName() === 'mysql') {
            $this->addForeign('cart_items', 'cart_id', 'carts', 'cascade');
            $this->addForeign('orders', 'cart_id', 'carts', 'set null');
            $this->addForeign('order_items', 'order_id', 'orders', 'cascade');
            $this->addForeign('order_discounts', 'order_id', 'orders', 'cascade');
            $this->addForeign('order_discounts', 'discount_id', 'discounts', 'restrict');
            $this->addForeign('warranty_requests', 'order_id', 'orders', 'cascade');
            $this->addForeign('warranty_requests', 'order_item_id', 'order_items', 'cascade');
            $this->addForeign('refund_requests', 'order_id', 'orders', 'cascade');
            $this->addForeign('complaints', 'order_id', 'orders', 'cascade');
            $this->addForeign('order_histories', 'order_id', 'orders', 'cascade');
            $this->addForeign('notifications', 'order_id', 'orders', 'set null');
        }
    }

    public function down(): void
    {
        // Storage-engine and integrity hardening is intentionally one-way.
    }

    private function cleanupLegacyData(): void
    {
        $this->deleteOrphans('cart_items', 'cart_id', 'carts');
        $this->nullOrphans('orders', 'cart_id', 'carts');
        $this->deleteOrphans('order_items', 'order_id', 'orders');
        $this->deleteOrphans('order_discounts', 'order_id', 'orders');
        $this->deleteOrphans('order_discounts', 'discount_id', 'discounts');
        $this->deleteOrphans('warranty_requests', 'order_id', 'orders');
        $this->deleteOrphans('warranty_requests', 'order_item_id', 'order_items');
        $this->deleteOrphans('refund_requests', 'order_id', 'orders');
        $this->deleteOrphans('complaints', 'order_id', 'orders');
        $this->deleteOrphans('order_histories', 'order_id', 'orders');
        $this->deleteOrphans('notifications', 'order_id', 'orders');

        $this->mergeDuplicateItems('cart_items', ['cart_id', 'product_variant_id']);
        $this->mergeDuplicateItems('order_items', ['order_id', 'product_variant_id']);
        $this->deduplicate('order_discounts', ['order_id', 'discount_id'], 'discount_amount');
        $this->deduplicate('refund_requests', ['order_id']);

        $schema = Schema::connection(self::CONNECTION);

        if ($schema->hasTable('orders') && $schema->hasColumn('orders', 'inventory_reference')) {
            DB::connection(self::CONNECTION)->table('orders')
                ->where('inventory_reference', '')
                ->update(['inventory_reference' => null]);
            $duplicates = DB::connection(self::CONNECTION)->table('orders')->select('inventory_reference')
                ->selectRaw('MIN(id) as keeper_id')
                ->whereNotNull('inventory_reference')
                ->groupBy('inventory_reference')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $duplicate) {
                DB::connection(self::CONNECTION)->table('orders')
                    ->where('inventory_reference', $duplicate->inventory_reference)
                    ->where('id', '<>', $duplicate->keeper_id)
                    ->update([
                        'inventory_reference' => null,
                        'inventory_state' => null,
                        'inventory_updated_at' => null,
                    ]);
            }
        }
    }

    private function deleteOrphans(string $child, string $foreignKey, string $parent): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (
            ! $schema->hasTable($child)
            || ! $schema->hasTable($parent)
            || ! $schema->hasColumn($child, $foreignKey)
        ) {
            return;
        }

        DB::connection(self::CONNECTION)->table($child)
            ->whereNotNull($foreignKey)
            ->whereNotIn($foreignKey, fn ($query) => $query->select('id')->from($parent))
            ->delete();
    }

    private function nullOrphans(string $child, string $foreignKey, string $parent): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (
            ! $schema->hasTable($child)
            || ! $schema->hasTable($parent)
            || ! $schema->hasColumn($child, $foreignKey)
        ) {
            return;
        }

        DB::connection(self::CONNECTION)->table($child)
            ->whereNotNull($foreignKey)
            ->whereNotIn($foreignKey, fn ($query) => $query->select('id')->from($parent))
            ->update([$foreignKey => null]);
    }

    private function mergeDuplicateItems(string $table, array $keys): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable($table)) {
            return;
        }

        $duplicates = DB::connection(self::CONNECTION)->table($table)
            ->select($keys)
            ->selectRaw('MIN(id) as keeper_id')
            ->groupBy($keys)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $query = DB::connection(self::CONNECTION)->table($table);

            foreach ($keys as $key) {
                $query->where($key, $duplicate->{$key});
            }

            $rows = $query->orderBy('id')->get();
            $update = [];

            if ($schema->hasColumn($table, 'quantity')) {
                $update['quantity'] = $rows->sum(fn (object $row): int => (int) $row->quantity);
            }

            if ($schema->hasColumn($table, 'subtotal')) {
                $update['subtotal'] = $rows->sum(fn (object $row): float => (float) $row->subtotal);
            }

            if ($update !== []) {
                DB::connection(self::CONNECTION)->table($table)
                    ->where('id', $duplicate->keeper_id)
                    ->update($update);
            }

            DB::connection(self::CONNECTION)->table($table)
                ->whereIn('id', $rows->pluck('id')->reject(fn ($id) => (int) $id === (int) $duplicate->keeper_id)->all())
                ->delete();
        }
    }

    private function deduplicate(string $table, array $keys, ?string $maxColumn = null): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable($table)) {
            return;
        }

        $duplicates = DB::connection(self::CONNECTION)->table($table)
            ->select($keys)
            ->selectRaw('MIN(id) as keeper_id')
            ->groupBy($keys)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $query = DB::connection(self::CONNECTION)->table($table);

            foreach ($keys as $key) {
                $query->where($key, $duplicate->{$key});
            }

            if ($maxColumn && $schema->hasColumn($table, $maxColumn)) {
                DB::connection(self::CONNECTION)->table($table)
                    ->where('id', $duplicate->keeper_id)
                    ->update([$maxColumn => (float) (clone $query)->max($maxColumn)]);
            }

            (clone $query)->where('id', '<>', $duplicate->keeper_id)->delete();
        }
    }

    private function addUnique(string $table, array $columns, string $name): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        foreach ($columns as $column) {
            if (! $schema->hasColumn($table, $column)) {
                return;
            }
        }

        $schema->table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $name));
    }

    private function addForeign(string $table, string $column, string $parent, string $onDelete): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $name = "{$table}_{$column}_foreign";

        if (
            ! $schema->hasTable($table)
            || ! $schema->hasTable($parent)
            || ! $schema->hasColumn($table, $column)
            || $this->foreignExists($table, $name)
        ) {
            return;
        }

        $schema->table($table, function (Blueprint $blueprint) use ($column, $parent, $onDelete, $name): void {
            $blueprint->foreign($column, $name)
                ->references('id')
                ->on($parent)
                ->onDelete($onDelete);
        });
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

    private function foreignExists(string $table, string $foreign): bool
    {
        $connection = DB::connection(self::CONNECTION);

        return $connection->table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $connection->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreign)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
