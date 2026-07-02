<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\WarrantyPolicy;
use App\Services\CatalogCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    private const RESOURCES = [
        'banners' => ['model' => Banner::class],
        'brands' => ['model' => Brand::class],
        'categories' => ['model' => Category::class],
        'products' => ['model' => Product::class, 'relations' => ['category', 'brand', 'variants', 'images', 'warrantyPolicy']],
        'product-variants' => ['model' => ProductVariant::class, 'relations' => ['product', 'images', 'inventory']],
        'product_variants' => ['model' => ProductVariant::class, 'relations' => ['product', 'images', 'inventory']],
        'inventories' => ['model' => Inventory::class, 'relations' => ['variant']],
        'inventory-transactions' => ['model' => InventoryTransaction::class, 'relations' => ['variant']],
        'inventory_transactions' => ['model' => InventoryTransaction::class, 'relations' => ['variant']],
        'product-images' => ['model' => ProductImage::class, 'relations' => ['product', 'variant']],
        'product_images' => ['model' => ProductImage::class, 'relations' => ['product', 'variant']],
        'warranty-policies' => ['model' => WarrantyPolicy::class],
        'warranty_policies' => ['model' => WarrantyPolicy::class],
    ];

    public function __construct(private readonly CatalogCache $cache) {}

    public function index(Request $request): JsonResponse
    {
        [$modelClass, $relations] = $this->resolve($request);

        $query = $modelClass::query();

        if ($relations) {
            $query->with($relations);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('id')->get(),
        ]);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        [$modelClass, $relations] = $this->resolve($request);
        $query = $modelClass::query();

        if ($relations) {
            $query->with($relations);
        }

        $record = $query->find((int) $id);

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay du lieu',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        [$modelClass, $relations, $resource] = $this->resolve($request);
        $model = new $modelClass;
        $record = $modelClass::create($this->payload($request, $model, $resource));
        $this->bumpPublicCatalogCache($resource);

        return response()->json([
            'success' => true,
            'message' => 'Tao du lieu thanh cong',
            'data' => $this->fresh($record, $relations),
        ], 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        [$modelClass, $relations, $resource] = $this->resolve($request);
        $record = $modelClass::find((int) $id);

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay du lieu',
            ], 404);
        }

        $record->fill($this->payload($request, $record, $resource));
        $record->save();
        $this->bumpPublicCatalogCache($resource);

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat du lieu thanh cong',
            'data' => $this->fresh($record, $relations),
        ]);
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        [$modelClass,, $resource] = $this->resolve($request);
        $record = $modelClass::find((int) $id);

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay du lieu',
            ], 404);
        }

        $record->delete();
        $this->bumpPublicCatalogCache($resource);

        return response()->json([
            'success' => true,
            'message' => 'Xoa du lieu thanh cong',
        ]);
    }

    private function resolve(Request $request): array
    {
        $resource = (string) $request->route('resource');
        $config = self::RESOURCES[$resource] ?? null;

        abort_if(! $config, 404, 'Resource khong duoc ho tro');

        return [
            $config['model'],
            $config['relations'] ?? [],
            $resource,
        ];
    }

    private function payload(Request $request, Model $model, string $resource): array
    {
        $payload = collect($request->all())
            ->only($model->getFillable())
            ->all();

        if (isset($payload['specifications']) && is_string($payload['specifications'])) {
            $decoded = json_decode($payload['specifications'], true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $payload['specifications'] = $decoded;
            }
        }

        return $payload;
    }

    private function fresh(Model $record, array $relations): Model
    {
        return $record->fresh($relations) ?? $record;
    }

    private function bumpPublicCatalogCache(string $resource): void
    {
        if (in_array($resource, ['banners', 'brands', 'categories', 'products'], true)) {
            $this->cache->bump();
        }
    }
}
