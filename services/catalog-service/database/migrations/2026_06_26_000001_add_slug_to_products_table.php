<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const CONNECTION = 'bstore_catalog';

    private const TABLE = 'products';

    // Áp dụng thay đổi cấu trúc cơ sở dữ liệu.
    public function up(): void
    {
        if (! Schema::connection(self::CONNECTION)->hasTable(self::TABLE)) {
            return;
        }

        $addedSlugColumn = false;

        if (! Schema::connection(self::CONNECTION)->hasColumn(self::TABLE, 'slug')) {
            Schema::connection(self::CONNECTION)->table(self::TABLE, function (Blueprint $table): void {
                $table->string('slug', 191)->nullable()->after('name');
            });

            $addedSlugColumn = true;
        }

        $this->backfillSlugs();

        if ($addedSlugColumn) {
            $this->makeSlugNotNullable();
        }

        if (! $this->hasUniqueSlugIndex()) {
            Schema::connection(self::CONNECTION)->table(self::TABLE, function (Blueprint $table): void {
                $table->unique('slug');
            });
        }
    }

    // Hoàn tác thay đổi cấu trúc cơ sở dữ liệu.
    public function down(): void
    {
        //
    }

    // Thực hiện backfill slugs.
    private function backfillSlugs(): void
    {
        $products = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->select(['id', 'name', 'slug'])
            ->orderBy('id')
            ->get();

        $this->moveSlugsToTemporaryValues($products);

        $usedSlugs = [];

        foreach ($products as $product) {
            $slug = $this->nextSlug($this->baseSlug((string) $product->name), $usedSlugs);
            $usedSlugs[$slug] = true;

            DB::connection(self::CONNECTION)
                ->table(self::TABLE)
                ->where('id', $product->id)
                ->update(['slug' => $slug]);
        }
    }

    // Thực hiện move slugs cho temporary values.
    private function moveSlugsToTemporaryValues(Collection $products): void
    {
        $reservedSlugs = [];

        foreach ($products as $product) {
            $slug = $this->normalizeExistingSlug($product->slug);

            if ($slug !== '') {
                $reservedSlugs[$slug] = true;
            }
        }

        foreach ($products as $product) {
            $temporarySlug = $this->nextTemporarySlug($product->id, $reservedSlugs);
            $reservedSlugs[$temporarySlug] = true;

            DB::connection(self::CONNECTION)
                ->table(self::TABLE)
                ->where('id', $product->id)
                ->update(['slug' => $temporarySlug]);
        }
    }

    // Thực hiện base slug.
    private function baseSlug(string $name): string
    {
        return Str::limit(Str::slug($name) ?: 'product', 191, '');
    }

    // Thực hiện next slug.
    private function nextSlug(string $baseSlug, array $usedSlugs): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while (isset($usedSlugs[$slug])) {
            $suffixText = '-'.$suffix;
            $slug = Str::limit($baseSlug, 191 - strlen($suffixText), '').$suffixText;
            $suffix++;
        }

        return $slug;
    }

    // Thực hiện next temporary slug.
    private function nextTemporarySlug(int|string $productId, array $reservedSlugs): string
    {
        $baseSlug = Str::limit('__slug_migration_'.$productId, 191, '');
        $slug = $baseSlug;
        $suffix = 2;

        while (isset($reservedSlugs[$slug])) {
            $suffixText = '_'.$suffix;
            $slug = Str::limit($baseSlug, 191 - strlen($suffixText), '').$suffixText;
            $suffix++;
        }

        return $slug;
    }

    // Chuẩn hóa existing slug.
    private function normalizeExistingSlug(?string $slug): string
    {
        return trim((string) $slug);
    }

    // Xây dựng hoặc chuyển đổi slug not nullable.
    private function makeSlugNotNullable(): void
    {
        if (DB::connection(self::CONNECTION)->getDriverName() !== 'mysql') {
            return;
        }

        DB::connection(self::CONNECTION)
            ->statement('ALTER TABLE '.self::TABLE.' MODIFY slug VARCHAR(191) NOT NULL');
    }

    // Kiểm tra duy nhất slug chỉ mục.
    private function hasUniqueSlugIndex(): bool
    {
        return $this->uniqueSlugIndexes()->isNotEmpty();
    }

    // Thực hiện duy nhất slug indexes.
    private function uniqueSlugIndexes(): Collection
    {
        $connection = DB::connection(self::CONNECTION);

        return match ($connection->getDriverName()) {
            'mysql' => $this->mysqlUniqueSlugIndexes(),
            'sqlite' => $this->sqliteUniqueSlugIndexes(),
            default => collect(),
        };
    }

    // Thực hiện mysql duy nhất slug indexes.
    private function mysqlUniqueSlugIndexes(): Collection
    {
        $connection = DB::connection(self::CONNECTION);

        $indexes = $connection
            ->table('information_schema.STATISTICS')
            ->select(['INDEX_NAME', 'COLUMN_NAME'])
            ->where('TABLE_SCHEMA', $connection->getDatabaseName())
            ->where('TABLE_NAME', self::TABLE)
            ->where('NON_UNIQUE', 0)
            ->where('INDEX_NAME', '<>', 'PRIMARY')
            ->orderBy('INDEX_NAME')
            ->orderBy('SEQ_IN_INDEX')
            ->get();

        return $indexes
            ->groupBy('INDEX_NAME')
            ->filter(fn (Collection $columns): bool => $columns->pluck('COLUMN_NAME')->all() === ['slug'])
            ->keys();
    }

    // Thực hiện sqlite duy nhất slug indexes.
    private function sqliteUniqueSlugIndexes(): Collection
    {
        $indexes = collect(DB::connection(self::CONNECTION)->select("PRAGMA index_list('".self::TABLE."')"));

        return $indexes
            ->filter(fn (object $index): bool => (bool) $index->unique)
            ->filter(function (object $index): bool {
                $columns = collect(DB::connection(self::CONNECTION)->select(
                    "PRAGMA index_info('".str_replace("'", "''", $index->name)."')"
                ));

                return $columns->pluck('name')->all() === ['slug'];
            })
            ->pluck('name');
    }
};
