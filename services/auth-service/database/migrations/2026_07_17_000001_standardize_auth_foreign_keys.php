<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'bstore_auth';

    /**
     * These are the constraints created by the earlier Auth Service migrations.
     * They are replaced only to standardize their names and ON UPDATE action.
     */
    private const LEGACY_CONSTRAINTS = [
        'fk_users_role_id' => 'users_role_id_foreign',
        'fk_user_addresses_user_id' => 'user_addresses_user_id_foreign',
        'fk_auth_sessions_user_id' => 'auth_sessions_user_id_foreign',
    ];

    private const RELATIONS = [
        [
            'name' => 'fk_users_role_id',
            'table' => 'users',
            'column' => 'role_id',
            'parent' => 'roles',
            'nullable' => true,
            'on_delete' => 'SET NULL',
        ],
        [
            'name' => 'fk_user_addresses_user_id',
            'table' => 'user_addresses',
            'column' => 'user_id',
            'parent' => 'users',
            'nullable' => false,
            'on_delete' => 'CASCADE',
        ],
        [
            'name' => 'fk_auth_sessions_user_id',
            'table' => 'auth_sessions',
            'column' => 'user_id',
            'parent' => 'users',
            'nullable' => false,
            'on_delete' => 'CASCADE',
        ],
    ];

    // Áp dụng thay đổi cấu trúc cơ sở dữ liệu.
    public function up(): void
    {
        $database = DB::connection(self::CONNECTION);

        // SQLite is used by the test suite and does not expose MySQL engine or
        // information_schema metadata. Production Auth Service uses MySQL.
        if ($database->getDriverName() !== 'mysql') {
            return;
        }

        $this->assertSchemaIsReady();
        $this->assertNoOrphans();
        $this->assertOnlyExpectedForeignKeys();

        foreach ($this->authTables() as $table) {
            $this->convertToInnoDbIfNeeded($table);
            $this->assertInnoDb($table);
        }

        // Drop only the known, earlier constraint names. This is necessary if
        // a child column must be aligned before the standardized FK is added.
        foreach (self::RELATIONS as $relation) {
            $this->dropForeignIfExists($relation['table'], self::LEGACY_CONSTRAINTS[$relation['name']]);
        }

        foreach (self::RELATIONS as $relation) {
            $this->alignChildColumnWithParent($relation);
            $this->dropForeignIfExists($relation['table'], $relation['name']);
            $this->addForeignKey($relation);
        }
    }

    // Hoàn tác thay đổi cấu trúc cơ sở dữ liệu.
    public function down(): void
    {
        $database = DB::connection(self::CONNECTION);

        if ($database->getDriverName() !== 'mysql') {
            return;
        }

        // Restore the prior constraints exactly; engines and column types are
        // intentionally retained because reverting them can truncate data.
        foreach (self::RELATIONS as $relation) {
            $this->dropForeignIfExists($relation['table'], $relation['name']);

            if (! $this->foreignExists($relation['table'], self::LEGACY_CONSTRAINTS[$relation['name']])) {
                $this->addForeignKey($relation, self::LEGACY_CONSTRAINTS[$relation['name']], 'RESTRICT');
            }
        }
    }

    // Thực hiện assert schema is ready.
    private function assertSchemaIsReady(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        foreach (self::RELATIONS as $relation) {
            if (! $schema->hasTable($relation['table']) || ! $schema->hasTable($relation['parent'])
                || ! $schema->hasColumn($relation['table'], $relation['column'])
                || ! $schema->hasColumn($relation['parent'], 'id')) {
                throw new \RuntimeException(sprintf(
                    'Không thể thêm %s: thiếu bảng hoặc cột bắt buộc của Dịch vụ xác thực.',
                    $relation['name'],
                ));
            }
        }
    }

    // Thực hiện assert no orphans.
    private function assertNoOrphans(): void
    {
        $database = DB::connection(self::CONNECTION);

        foreach (self::RELATIONS as $relation) {
            $count = $database->table($relation['table'].' as child')
                ->leftJoin($relation['parent'].' as parent', 'parent.id', '=', 'child.'.$relation['column'])
                ->whereNotNull('child.'.$relation['column'])
                ->whereNull('parent.id')
                ->count();

            if ($count > 0) {
                throw new \RuntimeException(sprintf(
                    'Không thể thêm %s: tìm thấy %d bản ghi mồ côi. Hãy xử lý thủ công trước khi chạy lại migration.',
                    $relation['name'],
                    $count,
                ));
            }
        }
    }

    // Thực hiện assert only expected khóa ngoại keys.
    private function assertOnlyExpectedForeignKeys(): void
    {
        foreach (self::RELATIONS as $relation) {
            $foreignKeys = DB::connection(self::CONNECTION)->select(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
                   AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$relation['table'], $relation['column']],
            );

            foreach ($foreignKeys as $foreignKey) {
                $name = $foreignKey->CONSTRAINT_NAME;

                if (! in_array($name, [$relation['name'], self::LEGACY_CONSTRAINTS[$relation['name']]], true)) {
                    throw new \RuntimeException(sprintf(
                        'Không thể chuẩn hóa %s: khóa ngoại ngoài dự kiến %s đang tồn tại trên %s.%s.',
                        $relation['name'],
                        $name,
                        $relation['table'],
                        $relation['column'],
                    ));
                }
            }
        }
    }

    // Thực hiện xác thực tables.
    private function authTables(): array
    {
        return ['roles', 'users', 'user_addresses', 'auth_sessions'];
    }

    // Xây dựng hoặc chuyển đổi cho inno db if needed.
    private function convertToInnoDbIfNeeded(string $table): void
    {
        $database = DB::connection(self::CONNECTION);
        $engine = $this->tableEngine($table);

        if ($engine === 'MYISAM') {
            $database->statement("ALTER TABLE `{$table}` ENGINE = InnoDB");
        }
    }

    // Thực hiện assert inno db.
    private function assertInnoDb(string $table): void
    {
        if ($this->tableEngine($table) !== 'INNODB') {
            throw new \RuntimeException("Không thể thêm khóa ngoại: {$table} không sử dụng storage engine tương thích với InnoDB.");
        }
    }

    // Thực hiện bảng engine.
    private function tableEngine(string $table): string
    {
        $row = DB::connection(self::CONNECTION)->selectOne(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        );

        return strtoupper((string) ($row?->ENGINE ?? ''));
    }

    // Thực hiện align child cột cùng parent.
    private function alignChildColumnWithParent(array $relation): void
    {
        $database = DB::connection(self::CONNECTION);
        $parent = $database->selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$relation['parent'], 'id'],
        );
        $child = $database->selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$relation['table'], $relation['column']],
        );

        $parentType = (string) ($parent?->COLUMN_TYPE ?? '');
        $childType = (string) ($child?->COLUMN_TYPE ?? '');
        $shouldBeNullable = $relation['nullable'] ? 'YES' : 'NO';

        if ($parentType === '' || $childType === '') {
            throw new \RuntimeException("Không thể kiểm tra kiểu cột cho {$relation['name']}.");
        }

        if (strtolower($childType) !== strtolower($parentType) || $child->IS_NULLABLE !== $shouldBeNullable) {
            $nullability = $relation['nullable'] ? 'NULL' : 'NOT NULL';
            $database->statement("ALTER TABLE `{$relation['table']}` MODIFY `{$relation['column']}` {$parentType} {$nullability}");
        }
    }

    // Tạo hoặc lưu khóa ngoại khóa.
    private function addForeignKey(array $relation, ?string $name = null, string $onUpdate = 'CASCADE'): void
    {
        $name ??= $relation['name'];
        $database = DB::connection(self::CONNECTION);

        $database->statement(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`id`) ON UPDATE %s ON DELETE %s',
            $relation['table'],
            $name,
            $relation['column'],
            $relation['parent'],
            $onUpdate,
            $relation['on_delete'],
        ));
    }

    // Thực hiện drop khóa ngoại if tồn tại.
    private function dropForeignIfExists(string $table, string $name): void
    {
        if (! $this->foreignExists($table, $name)) {
            return;
        }

        try {
            DB::connection(self::CONNECTION)->statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        } catch (Exception $exception) {
            throw new \RuntimeException("Không thể xóa khóa ngoại {$name} khỏi {$table}.", previous: $exception);
        }
    }

    // Thực hiện khóa ngoại tồn tại.
    private function foreignExists(string $table, string $name): bool
    {
        return DB::connection(self::CONNECTION)->table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection(self::CONNECTION)->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
