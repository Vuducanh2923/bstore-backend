<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'bstore_payment';

    public function up(): void
    {
        $db = DB::connection(self::CONNECTION);
        $schema = Schema::connection(self::CONNECTION);

        if ($db->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['payments', 'payment_transactions', 'invoices'] as $table) {
            if ($schema->hasTable($table)) {
                $db->statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
            }
        }

        if ($schema->hasTable('payments')) {
            $db->statement('CREATE TEMPORARY TABLE `duplicate_payment_ids` AS SELECT `id` FROM (SELECT `id`, ROW_NUMBER() OVER (PARTITION BY `order_id` ORDER BY CASE `status` WHEN \'paid\' THEN 5 WHEN \'partially_refunded\' THEN 4 WHEN \'refunded\' THEN 3 WHEN \'pending\' THEN 2 ELSE 1 END DESC, `id` DESC) rn FROM `payments`) ranked WHERE rn > 1');
            if ($schema->hasTable('payment_transactions')) {
                $db->statement('DELETE t FROM `payment_transactions` t JOIN `duplicate_payment_ids` d ON d.`id` = t.`payment_id`');
                $db->statement('DELETE t FROM `payment_transactions` t LEFT JOIN `payments` p ON p.`id` = t.`payment_id` WHERE p.`id` IS NULL');
                $db->statement('DELETE t1 FROM `payment_transactions` t1 JOIN `payment_transactions` t2 ON t1.`transaction_code` = t2.`transaction_code` AND t1.`provider` = t2.`provider` AND t1.`id` < t2.`id`');
            }
            if ($schema->hasTable('invoices')) {
                $db->statement('DELETE i FROM `invoices` i JOIN `duplicate_payment_ids` d ON d.`id` = i.`payment_id`');
                $db->statement('DELETE i FROM `invoices` i LEFT JOIN `payments` p ON p.`id` = i.`payment_id` WHERE p.`id` IS NULL');
                $db->statement('DELETE i1 FROM `invoices` i1 JOIN `invoices` i2 ON (i1.`payment_id` = i2.`payment_id` OR i1.`order_id` = i2.`order_id`) AND i1.`id` < i2.`id`');
            }
            $db->statement('DELETE p FROM `payments` p JOIN `duplicate_payment_ids` d ON d.`id` = p.`id`');
            $db->statement('DROP TEMPORARY TABLE `duplicate_payment_ids`');
        }

        $this->addUnique('payments', ['order_id'], 'payments_order_id_unique');
        $this->addUnique('payments', ['transaction_code'], 'payments_transaction_code_unique');
        $this->addUnique('payment_transactions', ['transaction_code', 'provider'], 'payment_transactions_transaction_code_provider_unique');
        $this->addUnique('invoices', ['payment_id'], 'invoices_payment_id_unique');
        $this->addUnique('invoices', ['order_id'], 'invoices_order_id_unique');
        $this->addForeign('payment_transactions', 'payment_id', 'payments', 'cascade');
        $this->addForeign('invoices', 'payment_id', 'payments', 'cascade');
    }

    public function down(): void {}

    private function addUnique(string $table, array $columns, string $name): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable($table) || $this->indexExists($table, $name)
            || collect($columns)->contains(fn ($column) => ! $schema->hasColumn($table, $column))) {
            return;
        }

        $schema->table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $name));
    }

    private function addForeign(string $table, string $column, string $parent, string $onDelete): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $name = "{$table}_{$column}_foreign";

        if (! $schema->hasTable($table) || ! $schema->hasTable($parent) || ! $schema->hasColumn($table, $column) || $this->foreignExists($table, $name)) {
            return;
        }

        $schema->table($table, fn (Blueprint $blueprint) => $blueprint->foreign($column, $name)->references('id')->on($parent)->onDelete($onDelete));
    }

    private function indexExists(string $table, string $name): bool
    {
        $db = DB::connection(self::CONNECTION);

        return $db->table('information_schema.STATISTICS')->where('TABLE_SCHEMA', $db->getDatabaseName())
            ->where('TABLE_NAME', $table)->where('INDEX_NAME', $name)->exists();
    }

    private function foreignExists(string $table, string $name): bool
    {
        $db = DB::connection(self::CONNECTION);

        return $db->table('information_schema.TABLE_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', $db->getDatabaseName())
            ->where('TABLE_NAME', $table)->where('CONSTRAINT_NAME', $name)->where('CONSTRAINT_TYPE', 'FOREIGN KEY')->exists();
    }
};
