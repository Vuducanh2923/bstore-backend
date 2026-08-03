<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InventoryReservationException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\CatalogCache;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly CatalogCache $cache,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'page',
            'limit',
            'per_page',
            'category_id',
            'category',
            'brand_id',
            'brand',
            'keyword',
            'search',
            'status',
            'sort',
        ]);

        $payload = $this->paginatedProductPayload($this->productService->paginatedList($filters));

        return response()->json([
            'success' => true,
            'message' => 'Thành công.',
            ...$payload,
        ]);
    }

    public function sale(Request $request): JsonResponse
    {
        $payload = $this->paginatedProductPayload(
            $this->productService->salePaginatedList($request->only([
                'page',
                'limit',
                'per_page',
                'category_id',
                'category',
                'brand_id',
                'brand',
                'keyword',
                'search',
                'status',
            ]))
        );

        return response()->json([
            'success' => true,
            'message' => 'Thành công.',
            ...$payload,
        ]);
    }

    public function newProducts(Request $request): JsonResponse
    {
        $payload = $this->paginatedProductPayload(
            $this->productService->newPaginatedList($request->only([
                'page',
                'limit',
                'per_page',
                'category_id',
                'category',
                'brand_id',
                'brand',
                'keyword',
                'search',
                'status',
            ]))
        );

        return response()->json([
            'success' => true,
            'message' => 'Thành công.',
            ...$payload,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = $this->productService->findBySlug($slug);
        $payload = $product ? ProductResource::make($product)->resolve() : null;

        if ($payload === null) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay san pham',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function showById(int $id): JsonResponse
    {
        $product = $this->productService->findById($id);
        $payload = $product ? ProductResource::make($product)->resolve() : null;

        if ($payload === null) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay san pham',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $product = $this->productService->create($this->validatedData($request));
        $this->cache->bump();

        return response()->json([
            'success' => true,
            'message' => 'Tao san pham thanh cong',
            'data' => ProductResource::make($product),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay san pham',
            ], 404);
        }

        try {
            $product = $this->productService->update($product, $this->validatedData($request, false, $id));
        } catch (InventoryReservationException $exception) {
            return $this->inventoryConflict($exception);
        }
        $this->cache->bump();

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat san pham thanh cong',
            'data' => ProductResource::make($product),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay san pham',
            ], 404);
        }

        try {
            $this->productService->delete($product);
        } catch (InventoryReservationException $exception) {
            return $this->inventoryConflict($exception);
        }
        $this->cache->bump();

        return response()->json([
            'success' => true,
            'message' => 'Xoa san pham thanh cong',
        ]);
    }

    private function paginatedProductPayload($products): array
    {
        return [
            'data' => $products->items(),
            'pagination' => [
                'page' => $products->currentPage(),
                'limit' => $products->perPage(),
                'total' => $products->total(),
                'totalPages' => $products->lastPage(),
            ],
        ];
    }

    private function validatedData(Request $request, bool $creating = true, ?int $productId = null): array
    {
        $nameRule = $creating ? 'required' : 'sometimes';
        $requiredOnCreate = $creating ? 'required' : 'sometimes';
        $variantRule = $productId !== null
            ? Rule::exists('bstore_catalog.product_variants', 'id')->where('product_id', $productId)
            : Rule::in([]);

        return $request->validate([
            'category_id' => [$requiredOnCreate, 'integer', 'exists:bstore_catalog.categories,id'],
            'brand_id' => [$requiredOnCreate, 'integer', 'exists:bstore_catalog.brands,id'],
            'warranty_policy_id' => ['nullable', 'integer', 'exists:bstore_catalog.warranty_policies,id'],
            'name' => [$nameRule, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable'],
            'price' => [$requiredOnCreate, 'numeric', 'min:0'],
            'sale_percent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'status' => ['nullable', 'string', 'max:20'],
            'variants' => ['sometimes', 'array'],
            'variants.*.id' => ['sometimes', 'integer', 'min:1', $variantRule],
            'variants.*.color' => ['nullable', 'string', 'max:50'],
            'variants.*.ram' => ['nullable', 'string', 'max:50'],
            'variants.*.storage' => ['nullable', 'string', 'max:50'],
            'variants.*.specifications' => ['nullable', 'array'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:191', 'distinct'],
            'variants.*.barcode' => ['nullable', 'string', 'max:191'],
            'variants.*.status' => ['nullable', 'string', 'max:20'],
            'variants.*.quantity' => ['sometimes', 'integer', 'min:0'],
            'variants.*.stock' => ['sometimes', 'integer', 'min:0'],
            'images' => ['sometimes', 'array'],
            'images.*.product_variant_id' => ['nullable', 'integer', $variantRule],
            'images.*.image_url' => ['nullable', 'string', 'max:500', 'required_without:images.*.image'],
            'images.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'required_without:images.*.image_url'],
            'images.*.public_id' => ['nullable', 'string', 'max:255'],
            'images.*.is_thumbnail' => ['nullable', 'boolean'],
            'warranty_policy' => ['sometimes', 'nullable', 'array'],
            'warranty_policy.name' => ['required_with:warranty_policy', 'string', 'max:255'],
            'warranty_policy.duration_months' => ['nullable', 'integer', 'min:0'],
            'warranty_policy.warranty_months' => ['nullable', 'integer', 'min:0'],
            'warranty_policy.return_days' => ['nullable', 'integer', 'min:0'],
            'warranty_policy.exchange_days' => ['nullable', 'integer', 'min:0'],
            'warranty_policy.repair_support' => ['nullable', 'boolean'],
            'warranty_policy.repair_supported' => ['nullable', 'boolean'],
            'warranty_policy.description' => ['nullable', 'string'],
            'warranty_policy.status' => ['nullable', 'string', 'max:20'],
        ]);
    }

    private function inventoryConflict(InventoryReservationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
            'data' => $exception->context ?: null,
        ], $exception->httpStatus);
    }
}
