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
        $database = DB::connection(self::CONNECTION);
        $schema = Schema::connection(self::CONNECTION);
        $userIdType = $this->userIdColumnType();

        if (! $schema->hasTable('auth_sessions')) {
            $schema->create('auth_sessions', function (Blueprint $table) use ($database, $userIdType) {
                $table->engine = 'InnoDB';
                $table->uuid('id')->primary();

                if ($database->getDriverName() === 'mysql' && ! str_contains($userIdType, 'unsigned')) {
                    $table->bigInteger('user_id');
                } else {
                    $table->unsignedBigInteger('user_id');
                }

                $table->uuid('access_jti')->unique();
                $table->char('refresh_token_hash', 64)->unique();
                $table->timestamp('refresh_expires_at')->index();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if ($database->getDriverName() === 'mysql') {
            // A failed legacy FK attempt may leave the table behind. Align its
            // type with users.id and finish the migration idempotently.
            $database->statement("ALTER TABLE `auth_sessions` MODIFY `user_id` {$userIdType} NOT NULL");
        }

        if (! $this->foreignExists()) {
            $schema->table('auth_sessions', function (Blueprint $table) {
                $table->foreign('user_id', 'auth_sessions_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::connection(self::CONNECTION)->dropIfExists('auth_sessions');
    }

    private function userIdColumnType(): string
    {
        $database = DB::connection(self::CONNECTION);

        if ($database->getDriverName() !== 'mysql') {
            return 'bigint unsigned';
        }

        $column = $database->selectOne(
            "SELECT COLUMN_TYPE column_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'id'"
        );

        return (string) ($column?->column_type ?: 'bigint unsigned');
    }

    private function foreignExists(): bool
    {
        $database = DB::connection(self::CONNECTION);

        if ($database->getDriverName() !== 'mysql') {
            return false;
        }

        return $database->table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database->getDatabaseName())
            ->where('TABLE_NAME', 'auth_sessions')
            ->where('CONSTRAINT_NAME', 'auth_sessions_user_id_foreign')
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
