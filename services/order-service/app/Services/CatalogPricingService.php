<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class CatalogPricingService
{
    public function applyCurrentPrices(array $items): array
    {
        return $this->resolveOrderItems($items);
    }

    /**
     * Build immutable order/cart item snapshots exclusively from Catalog data.
     */
    public function resolveOrderItems(array $items): array
    {
        $variantIds = collect($items)
            ->pluck('product_variant_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        if ($variantIds->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Don hang phai co it nhat mot san pham'],
            ]);
        }

        if ($variantIds->unique()->count() !== $variantIds->count()) {
            throw ValidationException::withMessages([
                'items' => ['Moi bien the san pham chi duoc xuat hien mot lan'],
            ]);
        }

        [$productColumns, $variantColumns] = $this->requiredCatalogColumns();
        $select = [
            'product_variants.id as product_variant_id',
            'product_variants.product_id',
        ];

        foreach (['price', 'color', 'ram', 'storage', 'status'] as $column) {
            if (in_array($column, $variantColumns, true)) {
                $select[] = "product_variants.{$column} as variant_{$column}";
            }
        }

        foreach (['name', 'price', 'sale_price', 'is_sale', 'status'] as $column) {
            if (in_array($column, $productColumns, true)) {
                $select[] = "products.{$column} as product_{$column}";
            }
        }

        try {
            $catalogRows = DB::connection('bstore_catalog')
                ->table('product_variants')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereIn('product_variants.id', $variantIds->all())
                ->select($select)
                ->get()
                ->keyBy(fn (object $row): int => (int) $row->product_variant_id);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'items' => ['Khong the xac minh san pham tu Catalog'],
            ]);
        }

        $missingIds = $variantIds->reject(fn (int $id): bool => $catalogRows->has($id))->all();

        if ($missingIds !== []) {
            throw ValidationException::withMessages([
                'items' => ['Bien the san pham khong ton tai: '.implode(', ', $missingIds)],
            ]);
        }

        $images = $this->imagesByVariant($catalogRows);

        return collect($items)->map(function (array $requested) use ($catalogRows, $images): array {
            $variantId = (int) $requested['product_variant_id'];
            $row = $catalogRows->get($variantId);

            if (! $this->isActive($row->variant_status ?? null) || ! $this->isActive($row->product_status ?? null)) {
                throw ValidationException::withMessages([
                    'items' => ["Bien the san pham {$variantId} khong con kinh doanh"],
                ]);
            }

            return [
                'product_variant_id' => $variantId,
                'product_id' => (int) $row->product_id,
                'product_name' => (string) ($row->product_name ?? ('Product #'.$row->product_id)),
                'product_image' => $images->get($variantId),
                'color' => $row->variant_color ?? null,
                'ram' => $row->variant_ram ?? null,
                'storage' => $row->variant_storage ?? null,
                'price' => $this->effectivePrice($row),
                'quantity' => (int) $requested['quantity'],
            ];
        })->all();
    }

    private function requiredCatalogColumns(): array
    {
        try {
            $schema = Schema::connection('bstore_catalog');

            if (! $schema->hasTable('products') || ! $schema->hasTable('product_variants')) {
                throw new \RuntimeException('Catalog tables are missing.');
            }

            $productColumns = $schema->getColumnListing('products');
            $variantColumns = $schema->getColumnListing('product_variants');

            if (! in_array('product_id', $variantColumns, true)) {
                throw new \RuntimeException('Catalog variant product relation is missing.');
            }

            if (! in_array('price', $productColumns, true) && ! in_array('price', $variantColumns, true)) {
                throw new \RuntimeException('Catalog price is missing.');
            }

            return [$productColumns, $variantColumns];
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'items' => ['Catalog khong san sang de xac minh gia san pham'],
            ]);
        }
    }

    private function imagesByVariant(Collection $catalogRows): Collection
    {
        try {
            $schema = Schema::connection('bstore_catalog');

            if (! $schema->hasTable('product_images')) {
                return collect();
            }

            $columns = $schema->getColumnListing('product_images');

            if (! in_array('image_url', $columns, true)) {
                return collect();
            }

            $variantIds = $catalogRows->keys()->all();
            $productIds = $catalogRows->pluck('product_id')->map(fn ($id): int => (int) $id)->unique()->all();
            $query = DB::connection('bstore_catalog')->table('product_images')
                ->where(function ($query) use ($columns, $variantIds, $productIds): void {
                    if (in_array('product_variant_id', $columns, true)) {
                        $query->whereIn('product_variant_id', $variantIds);
                    }

                    if (in_array('product_id', $columns, true)) {
                        $method = in_array('product_variant_id', $columns, true) ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('product_id', $productIds);
                    }
                });

            if (in_array('is_thumbnail', $columns, true)) {
                $query->orderByDesc('is_thumbnail');
            }

            $images = $query->orderBy('id')->get();

            return $catalogRows->mapWithKeys(function (object $row, int $variantId) use ($images): array {
                $image = $images->first(fn (object $image): bool => (int) ($image->product_variant_id ?? 0) === $variantId)
                    ?? $images->first(fn (object $image): bool => (int) ($image->product_id ?? 0) === (int) $row->product_id);

                return [$variantId => $image->image_url ?? null];
            });
        } catch (Throwable $exception) {
            report($exception);

            return collect();
        }
    }

    private function effectivePrice(object $product): float
    {
        $variantPrice = isset($product->variant_price) ? (float) $product->variant_price : 0.0;
        $productPrice = isset($product->product_price) ? (float) $product->product_price : 0.0;
        $regularPrice = $variantPrice > 0 ? $variantPrice : $productPrice;
        $salePrice = isset($product->product_sale_price) ? (float) $product->product_sale_price : null;

        if (
            isset($product->product_is_sale)
            && (bool) $product->product_is_sale
            && $salePrice !== null
            && $salePrice > 0
            && $salePrice < $regularPrice
        ) {
            return $salePrice;
        }

        return $regularPrice;
    }

    private function isActive(mixed $status): bool
    {
        return $status === null || $status === '' || strtolower((string) $status) === 'active';
    }
}
