<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\WarrantyPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductService
{
    private const RELATIONS = [
        'category',
        'brand',
        'variants',
        'images',
        'warrantyPolicy',
    ];

    public function all(array $filters = []): Collection
    {
        $query = Product::with(self::RELATIONS);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['category'])) {
            $category = $filters['category'];

            $query->whereHas('category', function ($categoryQuery) use ($category) {
                $categoryQuery
                    ->where('slug', $category)
                    ->orWhere('name', 'like', '%'.$category.'%');
            });
        }

        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];

            $query->where(function ($productQuery) use ($keyword) {
                $productQuery
                    ->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('slug', 'like', '%'.$keyword.'%')
                    ->orWhere('description', 'like', '%'.$keyword.'%')
                    ->orWhereHas('brand', function ($brandQuery) use ($keyword) {
                        $brandQuery->where('name', 'like', '%'.$keyword.'%');
                    })
                    ->orWhereHas('category', function ($categoryQuery) use ($keyword) {
                        $categoryQuery->where('name', 'like', '%'.$keyword.'%');
                    });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('id')->get();
    }

    public function find(int $id): ?Product
    {
        return Product::with(self::RELATIONS)->find($id);
    }

    public function create(array $data): Product
    {
        return DB::connection('bstore_catalog')->transaction(function () use ($data) {
            $variants = $data['variants'] ?? [];
            $images = $data['images'] ?? [];
            $warrantyPolicy = $data['warranty_policy'] ?? null;

            unset($data['variants'], $data['images'], $data['warranty_policy']);

            if (is_array($warrantyPolicy)) {
                $data['warranty_policy_id'] = WarrantyPolicy::create($this->normalizeWarrantyPolicy($warrantyPolicy))->id;
            }

            $product = Product::create($data);
            $this->syncVariants($product->id, $variants);
            $this->syncImages($product->id, $images);

            return $product->fresh(self::RELATIONS);
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::connection('bstore_catalog')->transaction(function () use ($product, $data) {
            $hasVariants = array_key_exists('variants', $data);
            $hasImages = array_key_exists('images', $data);
            $hasWarrantyPolicy = array_key_exists('warranty_policy', $data);

            $variants = $data['variants'] ?? [];
            $images = $data['images'] ?? [];
            $warrantyPolicy = $data['warranty_policy'] ?? null;

            unset($data['variants'], $data['images'], $data['warranty_policy']);

            if ($hasWarrantyPolicy) {
                $this->applyWarrantyPolicy($product, $data, $warrantyPolicy);
            }

            $product->update($data);

            if ($hasVariants) {
                ProductVariant::where('product_id', $product->id)->delete();
                $this->syncVariants($product->id, $variants);
            }

            if ($hasImages) {
                ProductImage::where('product_id', $product->id)->delete();
                $this->syncImages($product->id, $images);
            }

            return $product->fresh(self::RELATIONS);
        });
    }

    public function delete(Product $product): void
    {
        DB::connection('bstore_catalog')->transaction(function () use ($product) {
            ProductVariant::where('product_id', $product->id)->delete();
            ProductImage::where('product_id', $product->id)->delete();
            $product->delete();
        });
    }

    private function syncVariants(int $productId, array $variants): void
    {
        foreach ($variants as $variant) {
            ProductVariant::create([
                ...$variant,
                'product_id' => $productId,
            ]);
        }
    }

    private function syncImages(int $productId, array $images): void
    {
        foreach ($images as $image) {
            ProductImage::create([
                ...$image,
                'product_id' => $productId,
            ]);
        }
    }

    private function applyWarrantyPolicy(Product $product, array &$productData, ?array $warrantyPolicy): void
    {
        if ($warrantyPolicy === null) {
            $productData['warranty_policy_id'] = null;
            return;
        }

        $policyData = $this->normalizeWarrantyPolicy($warrantyPolicy);

        if ($product->warranty_policy_id) {
            WarrantyPolicy::whereKey($product->warranty_policy_id)->update($policyData);
            $productData['warranty_policy_id'] = $product->warranty_policy_id;
            return;
        }

        $productData['warranty_policy_id'] = WarrantyPolicy::create($policyData)->id;
    }

    private function normalizeWarrantyPolicy(array $warrantyPolicy): array
    {
        if (isset($warrantyPolicy['warranty_months']) && !isset($warrantyPolicy['duration_months'])) {
            $warrantyPolicy['duration_months'] = $warrantyPolicy['warranty_months'];
        }

        if (array_key_exists('repair_supported', $warrantyPolicy) && !array_key_exists('repair_support', $warrantyPolicy)) {
            $warrantyPolicy['repair_support'] = $warrantyPolicy['repair_supported'];
        }

        unset($warrantyPolicy['warranty_months'], $warrantyPolicy['repair_supported']);

        return $warrantyPolicy;
    }
}
