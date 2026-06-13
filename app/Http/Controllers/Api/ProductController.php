<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->productService->all($request->only([
                'category_id',
                'category',
                'keyword',
                'status',
            ])),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->productService->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay san pham',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $product = $this->productService->create($this->validatedData($request));

        return response()->json([
            'success' => true,
            'message' => 'Tao san pham thanh cong',
            'data' => $product,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay san pham',
            ], 404);
        }

        $product = $this->productService->update($product, $this->validatedData($request, $id, false));

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat san pham thanh cong',
            'data' => $product,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay san pham',
            ], 404);
        }

        $this->productService->delete($product);

        return response()->json([
            'success' => true,
            'message' => 'Xoa san pham thanh cong',
        ]);
    }

    private function validatedData(Request $request, ?int $productId = null, bool $creating = true): array
    {
        $nameRule = $creating ? 'required' : 'sometimes';
        $requiredOnCreate = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'category_id' => [$requiredOnCreate, 'integer'],
            'brand_id' => [$requiredOnCreate, 'integer'],
            'warranty_policy_id' => ['nullable', 'integer'],
            'name' => [$nameRule, 'string', 'max:255'],
            'slug' => [$requiredOnCreate, 'string', 'max:191', Rule::unique('bstore_catalog.products', 'slug')->ignore($productId)],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable'],
            'price' => [$requiredOnCreate, 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:20'],
            'variants' => ['sometimes', 'array'],
            'variants.*.color' => ['nullable', 'string', 'max:50'],
            'variants.*.ram' => ['nullable', 'string', 'max:50'],
            'variants.*.storage' => ['nullable', 'string', 'max:50'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:191'],
            'variants.*.barcode' => ['nullable', 'string', 'max:191'],
            'variants.*.status' => ['nullable', 'string', 'max:20'],
            'images' => ['sometimes', 'array'],
            'images.*.product_variant_id' => ['nullable', 'integer'],
            'images.*.image_url' => ['required_with:images', 'string', 'max:255'],
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
}
