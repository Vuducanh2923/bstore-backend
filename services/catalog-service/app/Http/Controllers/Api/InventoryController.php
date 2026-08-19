<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{

    // Lấy toàn bộ dữ liệu.
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', $request->query('limit', 25))));
        $inventories = Inventory::query()
            ->with([
                'variant:id,product_id,color,ram,storage,status',
                'variant.product' => fn ($query) => $query
                    ->select(['id', 'name', 'slug'])
                    ->addSelect([
                        'thumbnail' => ProductImage::query()
                            ->select('image_url')
                            ->whereColumn('product_images.product_id', 'products.id')
                            ->orderByDesc('is_thumbnail')
                            ->orderBy('id')
                            ->limit(1),
                    ]),
            ])
            ->orderByDesc('id')
            ->paginate($perPage, ['id', 'product_variant_id', 'quantity', 'reserved_quantity'], 'page', max(1, (int) $request->query('page', 1)));

        return response()->json([
            'success' => true,
            'data' => $inventories->items(),
            'pagination' => [
                'page' => $inventories->currentPage(),
                'limit' => $inventories->perPage(),
                'total' => $inventories->total(),
                'totalPages' => $inventories->lastPage(),
            ],
        ]);
    }

    // Lấy toàn bộ dữ liệu.
    public function show(int $id): JsonResponse
    {
        $inventory = Inventory::query()->with('variant.product')->find($id);

        return $inventory
            ? response()->json(['success' => true, 'data' => $inventory])
            : $this->notFound();
    }

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:0'],
            'reserved_quantity' => ['sometimes', 'integer', 'min:0', Rule::in([0])],
        ]);

        $variant = ProductVariant::find($data['product_variant_id']);

        if (! $variant) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy biến thể sản phẩm',
                'data' => null,
            ], 422);
        }

        if (Inventory::where('product_variant_id', $variant->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tồn kho của biến thể đã tồn tại',
                'data' => null,
            ], 409);
        }

        $inventory = Inventory::create([
            'product_variant_id' => $variant->id,
            'quantity' => $data['quantity'],
            'reserved_quantity' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tạo tồn kho thành công',
            'data' => $inventory->fresh('variant.product'),
        ], 201);
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['sometimes', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'integer', 'min:0'],
            'reserved_quantity' => ['sometimes', 'integer', 'min:0'],
        ]);

        return DB::connection('bstore_catalog')->transaction(function () use ($data, $id): JsonResponse {
            $inventory = Inventory::query()->lockForUpdate()->find($id);

            if (! $inventory) {
                return $this->notFound();
            }

            if (
                isset($data['product_variant_id'])
                && (int) $data['product_variant_id'] !== (int) $inventory->product_variant_id
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể thay đổi biến thể của bản ghi tồn kho',
                    'data' => null,
                ], 422);
            }

            if (
                isset($data['reserved_quantity'])
                && (int) $data['reserved_quantity'] !== (int) $inventory->reserved_quantity
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Số lượng đã giữ chỉ được thay đổi bởi thao tác giữ tồn kho',
                    'data' => null,
                ], 422);
            }

            $quantity = array_key_exists('quantity', $data)
                ? (int) $data['quantity']
                : (int) $inventory->quantity;

            if ($quantity < (int) $inventory->reserved_quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Số lượng tồn kho không được nhỏ hơn số lượng đã giữ',
                    'data' => null,
                ], 422);
            }

            $inventory->quantity = $quantity;
            $inventory->save();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật tồn kho thành công',
                'data' => $inventory->fresh('variant.product'),
            ]);
        }, 3);
    }

    // Xóa hoặc hủy dữ liệu theo nghiệp vụ của hàm.
    public function destroy(int $id): JsonResponse
    {
        return DB::connection('bstore_catalog')->transaction(function () use ($id): JsonResponse {
            $inventory = Inventory::query()->lockForUpdate()->find($id);

            if (! $inventory) {
                return $this->notFound();
            }

            $hasReservations = InventoryReservation::query()
                ->where('product_variant_id', $inventory->product_variant_id)
                ->exists();
            $hasTransactions = InventoryTransaction::query()
                ->where('product_variant_id', $inventory->product_variant_id)
                ->exists();

            if ((int) $inventory->reserved_quantity > 0 || $hasReservations || $hasTransactions) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa tồn kho đã phát sinh giao dịch',
                    'data' => null,
                ], 409);
            }

            $inventory->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa tồn kho thành công',
            ]);
        }, 3);
    }

    // Thực hiện not found.
    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Không tìm thấy tồn kho',
            'data' => null,
        ], 404);
    }
}
