<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'bstore_payment';

    private const INDEXES = [
        'payments' => [
            'idx_payment_payments_status' => ['status'],
            'idx_payment_payments_transaction_code' => ['transaction_code'],
            'idx_payment_payments_order_status' => ['order_id', 'status'],
            'idx_payment_payments_paid_at' => ['paid_at'],
        ],
        'payment_transactions' => [
            'idx_payment_transactions_transaction_provider' => ['transaction_code', 'provider'],
            'idx_payment_transactions_payment_status' => ['payment_id', 'status'],
        ],
        'invoices' => [
            'idx_payment_invoices_payment_order' => ['payment_id', 'order_id'],
            'idx_payment_invoices_issued_at' => ['issued_at'],
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
