<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'bstore_auth';

    private const INDEXES = [
        'users' => [
            'idx_auth_users_status' => ['status'],
            'idx_auth_users_created_at' => ['created_at'],
            'idx_auth_users_role_status' => ['role_id', 'status'],
        ],
        'user_addresses' => [
            'idx_auth_user_addresses_user_default' => ['user_id', 'is_default'],
        ],
        'email_verifications' => [
            'idx_auth_email_verifications_email_type_verified' => ['email', 'type', 'verified_at'],
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
