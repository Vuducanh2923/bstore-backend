<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discount\StoreDiscountRequest;
use App\Http\Resources\DiscountResource;
use App\Models\Discount;
use App\Services\DiscountManagementService;
use App\Services\OrderDiscountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscountController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(
        private readonly DiscountManagementService $discounts,
        private readonly OrderDiscountService $orderDiscounts,
    ) {}

    // Thực hiện preview.
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'discount_code' => ['required', 'string', 'max:191', 'regex:/^\S+$/'],
            'subtotal' => ['required', 'numeric', 'gt:0'],
        ]);
        $subtotal = (float) $data['subtotal'];
        $discounts = $this->orderDiscounts->preview([[
            'discount_code' => strtoupper(trim((string) $data['discount_code'])),
        ]], $subtotal, $this->actorId($request));
        $discountAmount = (float) collect($discounts)->sum('discount_amount');

        return response()->json([
            'success' => true,
            'message' => 'Áp dụng mã giảm giá thành công',
            'data' => [
                'discount_code' => $discounts[0]['discount_code'] ?? strtoupper($data['discount_code']),
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'final_amount' => max($subtotal - $discountAmount, 0),
            ],
        ]);
    }

    // Lấy toàn bộ dữ liệu.
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'expired'])],
            'discount_type' => ['nullable', Rule::in(['percentage', 'fixed_amount'])],
            'validity' => ['nullable', Rule::in(['effective', 'expiring', 'expired'])],
            'sort_by' => ['nullable', Rule::in(['created_at', 'starts_at', 'ends_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $page = $this->discounts->paginated($filters);

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách mã giảm giá thành công',
            'data' => collect($page->items())
                ->map(fn (Discount $discount): array => $this->resource($this->discounts->hydrate($discount)))
                ->all(),
            'pagination' => [
                'page' => $page->currentPage(),
                'limit' => $page->perPage(),
                'total' => $page->total(),
                'totalPages' => $page->lastPage(),
            ],
        ]);
    }

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
    public function store(StoreDiscountRequest $request): JsonResponse
    {
        $discount = $this->discounts->create($request->validated(), $this->actorId($request));

        return response()->json([
            'success' => true,
            'message' => 'Thêm mã giảm giá thành công',
            'data' => $this->resource($discount),
        ], 201);
    }

    // Lấy toàn bộ dữ liệu.
    public function show(int|string $id): JsonResponse
    {
        $discount = $this->discounts->find((int) $id);

        if (! $discount) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Lấy chi tiết mã giảm giá thành công',
            'data' => $this->resource($discount),
        ]);
    }

    // Xóa hoặc hủy dữ liệu theo nghiệp vụ của hàm.
    public function destroy(int|string $id): JsonResponse
    {
        $result = $this->discounts->deleteOrDeactivate((int) $id);

        if (! $result) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => $result['action'] === 'deleted'
                ? 'Xóa mã giảm giá thành công'
                : 'Mã giảm giá đã được ngừng áp dụng',
            'data' => $this->resource($result['discount']),
        ]);
    }

    // Thực hiện deactivate.
    public function deactivate(int|string $id): JsonResponse
    {
        $discount = $this->discounts->deactivate((int) $id);

        if (! $discount) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Mã giảm giá đã được ngừng áp dụng',
            'data' => $this->resource($discount),
        ]);
    }

    // Thực hiện người thực hiện id.
    private function actorId(Request $request): int
    {
        return (int) data_get($request->attributes->get('auth_user', []), 'id', 0);
    }

    // Thực hiện tài nguyên.
    private function resource(Discount $discount): array
    {
        return (new DiscountResource($discount))->resolve();
    }

    // Thực hiện not found.
    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Không tìm thấy mã giảm giá',
            'errors' => (object) [],
        ], 404);
    }
}
