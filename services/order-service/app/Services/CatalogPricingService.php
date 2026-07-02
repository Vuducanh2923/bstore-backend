<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogPricingService
{
    private ?bool $catalogTablesAvailable = null;

    private ?array $productColumns = null;

    public function applyCurrentPrices(array $items): array
    {
        $prices = $this->pricesByVariantId($items);

        return array_map(function (array $item) use ($prices) {
            $variantId = (int) ($item['product_variant_id'] ?? 0);

            if ($variantId > 0 && $prices->has($variantId)) {
                $item['price'] = $prices->get($variantId);
            }

            return $item;
        }, $items);
    }

    private function pricesByVariantId(array $items): Collection
    {
        $variantIds = collect($items)
            ->pluck('product_variant_id')
            ->map(fn ($variantId) => (int) $variantId)
            ->filter(fn (int $variantId) => $variantId > 0)
            ->unique()
            ->values();

        if ($variantIds->isEmpty() || ! $this->catalogTablesAvailable()) {
            return collect();
        }

        $productColumns = $this->productColumns();

        if (! in_array('price', $productColumns, true)) {
            return collect();
        }

        $select = [
            'product_variants.id as product_variant_id',
            'product_variants.price as variant_price',
            'products.price',
        ];

        foreach (['sale_price', 'is_sale'] as $column) {
            if (in_array($column, $productColumns, true)) {
                $select[] = "products.{$column}";
            }
        }

        return DB::connection('bstore_catalog')
            ->table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereIn('product_variants.id', $variantIds->all())
            ->select($select)
            ->get()
            ->mapWithKeys(fn (object $product) => [
                (int) $product->product_variant_id => $this->effectivePrice($product),
            ]);
    }

    private function effectivePrice(object $product): float
    {
        $regularPrice = (float) ($product->variant_price > 0 ? $product->variant_price : $product->price);
        $salePrice = property_exists($product, 'sale_price') && $product->sale_price !== null
            ? (float) $product->sale_price
            : null;

        if (
            property_exists($product, 'is_sale')
            && (bool) $product->is_sale
            && $salePrice !== null
            && $salePrice > 0
            && $salePrice < $regularPrice
        ) {
            return $salePrice;
        }

        return $regularPrice;
    }

    private function catalogTablesAvailable(): bool
    {
        if ($this->catalogTablesAvailable !== null) {
            return $this->catalogTablesAvailable;
        }

        try {
            return $this->catalogTablesAvailable = Schema::connection('bstore_catalog')->hasTable('products')
                && Schema::connection('bstore_catalog')->hasTable('product_variants');
        } catch (\Throwable $exception) {
            report($exception);

            return $this->catalogTablesAvailable = false;
        }
    }

    private function productColumns(): array
    {
        if ($this->productColumns !== null) {
            return $this->productColumns;
        }

        try {
            return $this->productColumns = Schema::connection('bstore_catalog')->getColumnListing('products');
        } catch (\Throwable $exception) {
            report($exception);

            return $this->productColumns = [];
        }
    }
}
