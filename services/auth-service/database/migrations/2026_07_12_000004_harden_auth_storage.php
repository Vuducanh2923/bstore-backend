<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'bstore_auth';

    public function up(): void
    {
        $db = DB::connection(self::CONNECTION);
        $schema = Schema::connection(self::CONNECTION);

        if ($db->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['roles', 'users', 'user_addresses', 'email_verifications'] as $table) {
            if ($schema->hasTable($table)) {
                $db->statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
            }
        }

        if ($schema->hasTable('users')) {
            if (! $this->foreignExists('users', 'users_role_id_foreign')) {
                $roleId = $db->selectOne(
                    "SELECT COLUMN_TYPE column_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role_id'"
                );

                if ($roleId?->column_type) {
                    $db->statement("ALTER TABLE `users` MODIFY `role_id` {$roleId->column_type} NULL");
                }
            }

            $db->statement("UPDATE `users` SET `phone` = NULL WHERE TRIM(COALESCE(`phone`, '')) = ''");
            $db->statement('UPDATE `users` u JOIN (SELECT `phone`, MIN(`id`) keep_id FROM `users` WHERE `phone` IS NOT NULL GROUP BY `phone` HAVING COUNT(*) > 1) d ON d.`phone` = u.`phone` SET u.`phone` = NULL WHERE u.`id` <> d.keep_id');

            if ($schema->hasTable('roles')) {
                $db->statement('UPDATE `users` u LEFT JOIN `roles` r ON r.`id` = u.`role_id` SET u.`role_id` = NULL WHERE u.`role_id` IS NOT NULL AND r.`id` IS NULL');
            }
        }

        if ($schema->hasTable('user_addresses') && $schema->hasTable('users')) {
            $db->statement('DELETE a FROM `user_addresses` a LEFT JOIN `users` u ON u.`id` = a.`user_id` WHERE u.`id` IS NULL');
        }

        $this->addUnique('users', ['phone'], 'users_phone_unique');
        $this->addForeign('users', 'role_id', 'roles', 'set null');
        $this->addForeign('user_addresses', 'user_id', 'users', 'cascade');
    }

    public function down(): void
    {
        // Engine conversion and integrity cleanup are intentionally irreversible.
    }

    private function addUnique(string $table, array $columns, string $name): void
    {
        if (! $this->hasColumns($table, $columns) || $this->indexExists($table, $name)) {
            return;
        }

        Schema::connection(self::CONNECTION)->table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $name));
    }

    private function addForeign(string $table, string $column, string $parent, string $onDelete): void
    {
        $name = "{$table}_{$column}_foreign";

        if (! $this->hasColumns($table, [$column])
            || ! Schema::connection(self::CONNECTION)->hasTable($parent)
            || $this->foreignExists($table, $name)) {
            return;
        }

        Schema::connection(self::CONNECTION)->table($table, function (Blueprint $blueprint) use ($column, $parent, $onDelete, $name) {
            $blueprint->foreign($column, $name)->references('id')->on($parent)->onDelete($onDelete);
        });
    }

    private function hasColumns(string $table, array $columns): bool
    {
        $schema = Schema::connection(self::CONNECTION);

        return $schema->hasTable($table) && collect($columns)->every(fn ($column) => $schema->hasColumn($table, $column));
    }

    private function indexExists(string $table, string $name): bool
    {
        $db = DB::connection(self::CONNECTION);

        return $db->table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $db->getDatabaseName())->where('TABLE_NAME', $table)->where('INDEX_NAME', $name)->exists();
    }

    private function foreignExists(string $table, string $name): bool
    {
        $db = DB::connection(self::CONNECTION);

        return $db->table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $db->getDatabaseName())->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)->where('CONSTRAINT_TYPE', 'FOREIGN KEY')->exists();
    }
};
