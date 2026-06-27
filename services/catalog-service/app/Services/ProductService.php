<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\WarrantyPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductService
{
    private const DEFAULT_PER_PAGE = 12;

    private const MAX_PER_PAGE = 30;

    private const NEW_PRODUCTS_PER_PAGE = 20;

    private const RELATIONS = [
        'category',
        'brand',
        'variants.inventory',
        'images',
        'warrantyPolicy',
    ];

    private const LIST_COLUMNS = [
        'id',
        'name',
        'slug',
        'price',
        'sale_percent',
        'discount_percent',
        'sale_price',
        'is_sale',
        'rating',
        'thumbnail',
        'category_id',
        'brand_id',
        'status',
    ];

    public function __construct(private readonly CloudinaryService $cloudinaryService) {}

    public function paginatedList(
        array $filters = [],
        bool $saleOnly = false,
        int $defaultPerPage = self::DEFAULT_PER_PAGE,
        int $maxPerPage = self::MAX_PER_PAGE
    ): LengthAwarePaginator
    {
        $productColumns = $this->productColumns();
        $listColumns = array_values(array_intersect(self::LIST_COLUMNS, $productColumns));

        $query = Product::query()
            ->select($listColumns)
            ->with([
                'category' => fn ($categoryQuery) => $categoryQuery->select(['id', 'name', 'slug', 'status']),
                'brand' => fn ($brandQuery) => $brandQuery->select($this->brandRelationColumns()),
            ]);

        if (! in_array('thumbnail', $productColumns, true)) {
            $query->addSelect([
                'thumbnail' => ProductImage::query()
                    ->select('image_url')
                    ->whereColumn('product_images.product_id', 'products.id')
                    ->orderByDesc('is_thumbnail')
                    ->orderBy('id')
                    ->limit(1),
            ]);
        }

        if ($saleOnly) {
            $this->applySaleFilter($query, $productColumns);
        }

        $this->onlyActiveRelations($query, $productColumns);
        $this->applyListFilters($query, $filters);
        $this->applyListSort($query, (string) ($filters['sort'] ?? ''), $productColumns);

        $perPage = min(
            $maxPerPage,
            max(1, (int) ($filters['limit'] ?? $filters['per_page'] ?? $defaultPerPage))
        );
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn (Product $product): array => $this->listItem($product));
    }

    public function salePaginatedList(array $filters = []): LengthAwarePaginator
    {
        return $this->paginatedList([
            ...$filters,
            'sort' => 'latest',
        ], true);
    }

    public function newPaginatedList(array $filters = []): LengthAwarePaginator
    {
        return $this->paginatedList([
            ...$filters,
            'limit' => $filters['limit'] ?? $filters['per_page'] ?? self::NEW_PRODUCTS_PER_PAGE,
            'sort' => 'latest',
        ], false, self::NEW_PRODUCTS_PER_PAGE, self::NEW_PRODUCTS_PER_PAGE);
    }

    public function all(array $filters = []): Collection
    {
        $query = Product::with(self::RELATIONS);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['category'])) {
            $category = $filters['category'];

            $query->whereHas('category', function ($categoryQuery) use ($category) {
                $categoryQuery
                    ->where('slug', $category)
                    ->orWhere('name', 'like', '%'.$category.'%');
            });
        }

        if (! empty($filters['keyword'])) {
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

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('id')->get();
    }

    public function findBySlug(string $slug): ?Product
    {
        return Product::with(self::RELATIONS)
            ->where('slug', $slug)
            ->first();
    }

    public function findById(int $id): ?Product
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

            $data = $this->withSalePricing($data, $this->productColumns());

            $product = Product::create($data);
            $this->syncVariants($product->id, $variants);
            $this->syncImages($product->id, $images);

            return $product->fresh(self::RELATIONS);
        });
    }

    public function update(Product $product, array $data): Product
    {
        $hasImages = array_key_exists('images', $data);
        $oldImagePublicIds = [];
        $newImagePublicIds = [];

        if ($hasImages) {
            $oldImagePublicIds = $this->productImagePublicIds($product->id);
            $data['images'] = $this->withExistingProductImagePublicIds($product->id, $data['images'] ?? []);
            $newImagePublicIds = $this->imagePublicIds($data['images']);
        }

        $updatedProduct = DB::connection('bstore_catalog')->transaction(function () use ($product, $data) {
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

            $data = $this->withSalePricing($data, $this->productColumns(), $product);

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

        if ($hasImages) {
            $this->deleteCloudinaryImages(array_diff($oldImagePublicIds, $newImagePublicIds), false);
        }

        return $updatedProduct;
    }

    public function delete(Product $product): void
    {
        $this->deleteCloudinaryImages($this->productImagePublicIds($product->id));

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
            $image['image_url'] = trim((string) ($image['image_url'] ?? ''));
            $image['public_id'] = trim((string) ($image['public_id'] ?? '')) ?: null;

            ProductImage::create([
                ...$image,
                'product_id' => $productId,
            ]);
        }
    }

    private function productImagePublicIds(int $productId): array
    {
        return ProductImage::where('product_id', $productId)
            ->whereNotNull('public_id')
            ->pluck('public_id')
            ->map(fn ($publicId) => trim((string) $publicId))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function imagePublicIds(array $images): array
    {
        return collect($images)
            ->pluck('public_id')
            ->map(fn ($publicId) => trim((string) $publicId))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function withExistingProductImagePublicIds(int $productId, array $images): array
    {
        $publicIdsByUrl = ProductImage::where('product_id', $productId)
            ->whereNotNull('public_id')
            ->pluck('public_id', 'image_url')
            ->all();

        return array_map(function (array $image) use ($publicIdsByUrl) {
            $imageUrl = trim((string) ($image['image_url'] ?? ''));
            $publicId = trim((string) ($image['public_id'] ?? ''));

            if ($publicId === '' && $imageUrl !== '' && isset($publicIdsByUrl[$imageUrl])) {
                $image['public_id'] = $publicIdsByUrl[$imageUrl];
            }

            return $image;
        }, $images);
    }

    private function deleteCloudinaryImages(array $publicIds, bool $throw = true): void
    {
        foreach (array_unique(array_filter($publicIds)) as $publicId) {
            try {
                $this->cloudinaryService->deleteImage($publicId);
            } catch (\Throwable $exception) {
                if ($throw) {
                    throw $exception;
                }

                report($exception);
            }
        }
    }

    private function applySaleFilter($query, array $productColumns): void
    {
        $saleColumns = array_values(array_intersect(['discount_percent', 'sale_percent'], $productColumns));

        if ($saleColumns === []) {
            in_array('is_sale', $productColumns, true)
                ? $query->where('is_sale', true)
                : $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($saleQuery) use ($saleColumns) {
            foreach ($saleColumns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';

                $saleQuery->{$method}($column, '>', 0);
            }
        });
    }

    private function onlyActiveRelations($query, array $productColumns): void
    {
        if (in_array('category_id', $productColumns, true)) {
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('status', 'active'));
        }

        if (in_array('brand_id', $productColumns, true)) {
            $query->whereHas('brand', fn ($brandQuery) => $brandQuery->where('status', 'active'));
        }
    }

    private function applyListFilters($query, array $filters): void
    {
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['category'])) {
            $category = trim((string) $filters['category']);

            if (ctype_digit($category)) {
                $query->where('category_id', (int) $category);
            } else {
                $query->whereHas('category', function ($categoryQuery) use ($category) {
                    $categoryQuery
                        ->where('slug', $category)
                        ->orWhere('name', 'like', '%'.$category.'%');
                });
            }
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (! empty($filters['brand'])) {
            $brand = trim((string) $filters['brand']);

            if (ctype_digit($brand)) {
                $query->where('brand_id', (int) $brand);
            } else {
                $query->whereHas('brand', function ($brandQuery) use ($brand) {
                    $brandQuery
                        ->where('slug', $brand)
                        ->orWhere('name', 'like', '%'.$brand.'%');
                });
            }
        }

        $search = trim((string) ($filters['search'] ?? $filters['keyword'] ?? ''));

        if ($search !== '') {
            $query->where(function ($productQuery) use ($search) {
                $productQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhereHas('brand', function ($brandQuery) use ($search) {
                        $brandQuery->where('name', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('category', function ($categoryQuery) use ($search) {
                        $categoryQuery->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
    }

    private function listItem(Product $product): array
    {
        $salePercent = $this->loadedAttribute($product, 'sale_percent');
        $discountPercent = $this->resolvedDiscountPercent($product);
        $salePrice = $this->resolvedSalePrice($product, $discountPercent);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'original_price' => $product->price,
            'sale_percent' => $salePercent,
            'discount_percent' => $discountPercent,
            'sale_price' => $salePrice,
            'discounted_price' => $salePrice,
            'is_sale' => (bool) ($this->loadedAttribute($product, 'is_sale') ?? false) || (float) ($discountPercent ?? 0) > 0,
            'status' => $this->loadedAttribute($product, 'status'),
            'thumbnail' => $product->thumbnail,
            'category_id' => $this->loadedAttribute($product, 'category_id'),
            'category_name' => $product->category?->name,
            'brand_id' => $this->loadedAttribute($product, 'brand_id'),
            'brand_name' => $product->brand?->name,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
                'logo' => $product->brand->logo,
            ] : null,
            'rating' => $this->loadedAttribute($product, 'rating'),
        ];
    }

    private function resolvedDiscountPercent(Product $product): mixed
    {
        return $this->loadedAttribute($product, 'discount_percent')
            ?? $this->loadedAttribute($product, 'sale_percent');
    }

    private function resolvedSalePrice(Product $product, mixed $discountPercent): mixed
    {
        $salePrice = $this->loadedAttribute($product, 'sale_price');

        if ($salePrice !== null || $discountPercent === null || $discountPercent === '' || (float) $discountPercent <= 0) {
            return $salePrice;
        }

        return number_format((float) $product->price - ((float) $product->price * (float) $discountPercent / 100), 2, '.', '');
    }

    private function loadedAttribute(Product $product, string $attribute): mixed
    {
        return array_key_exists($attribute, $product->getAttributes())
            ? $product->getAttributeValue($attribute)
            : null;
    }

    private function applyListSort($query, string $sort, array $productColumns): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            'name_asc' => $query->orderBy('name')->orderByDesc('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'oldest', 'created_at_asc' => $this->orderByCreatedAt($query, $productColumns, 'asc'),
            'latest', 'newest', 'created_at_desc' => $this->orderByCreatedAt($query, $productColumns, 'desc'),
            default => $this->orderByCreatedAt($query, $productColumns, 'desc'),
        };
    }

    private function orderByCreatedAt($query, array $productColumns, string $direction): void
    {
        if (in_array('created_at', $productColumns, true)) {
            $query->orderBy('created_at', $direction)->orderByDesc('id');

            return;
        }

        $direction === 'asc'
            ? $query->orderBy('id')
            : $query->orderByDesc('id');
    }

    private function productColumns(): array
    {
        return Schema::connection('bstore_catalog')->getColumnListing('products');
    }

    private function brandRelationColumns(): array
    {
        $columns = ['id', 'name', 'slug', 'status'];

        if (Schema::connection('bstore_catalog')->hasColumn('brands', 'logo')) {
            $columns[] = 'logo';
        }

        return $columns;
    }

    private function withSalePricing(array $data, array $productColumns, ?Product $product = null): array
    {
        if (array_key_exists('discount_percent', $data) && ! array_key_exists('sale_percent', $data)) {
            $data['sale_percent'] = $data['discount_percent'];
        }

        if (array_key_exists('sale_percent', $data) && ! array_key_exists('discount_percent', $data)) {
            $data['discount_percent'] = $data['sale_percent'];
        }

        $saleColumns = ['sale_percent', 'sale_price', 'is_sale'];
        $hasDiscountColumn = in_array('discount_percent', $productColumns, true);

        if (! $hasDiscountColumn) {
            unset($data['discount_percent']);
        }

        if (array_diff($saleColumns, $productColumns) !== []) {
            foreach ($saleColumns as $column) {
                unset($data[$column]);
            }

            return $data;
        }

        $price = array_key_exists('price', $data)
            ? (float) $data['price']
            : (float) ($product?->price ?? 0);
        $salePercent = array_key_exists('sale_percent', $data)
            ? $data['sale_percent']
            : ($product?->getAttribute('sale_percent') ?? $product?->getAttribute('discount_percent'));

        if ($salePercent === null || $salePercent === '' || (float) $salePercent <= 0) {
            $data['sale_percent'] = null;
            if ($hasDiscountColumn) {
                $data['discount_percent'] = null;
            }
            $data['sale_price'] = null;
            $data['is_sale'] = false;

            return $data;
        }

        $salePercent = (float) $salePercent;

        $data['sale_percent'] = $salePercent;
        if ($hasDiscountColumn) {
            $data['discount_percent'] = $salePercent;
        }
        $data['sale_price'] = round($price - ($price * $salePercent / 100), 2);
        $data['is_sale'] = true;

        return $data;
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
        if (isset($warrantyPolicy['warranty_months']) && ! isset($warrantyPolicy['duration_months'])) {
            $warrantyPolicy['duration_months'] = $warrantyPolicy['warranty_months'];
        }

        if (array_key_exists('repair_supported', $warrantyPolicy) && ! array_key_exists('repair_support', $warrantyPolicy)) {
            $warrantyPolicy['repair_support'] = $warrantyPolicy['repair_supported'];
        }

        unset($warrantyPolicy['warranty_months'], $warrantyPolicy['repair_supported']);

        return $warrantyPolicy;
    }
}
