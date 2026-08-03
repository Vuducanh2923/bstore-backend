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
    public function __construct(
        private readonly DiscountManagementService $discounts,
        private readonly OrderDiscountService $orderDiscounts,
    ) {}

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
            'message' => 'Ap dung ma giam gia thanh cong',
            'data' => [
                'discount_code' => $discounts[0]['discount_code'] ?? strtoupper($data['discount_code']),
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'final_amount' => max($subtotal - $discountAmount, 0),
            ],
        ]);
    }

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
            'message' => 'Lay danh sach ma giam gia thanh cong',
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

    public function store(StoreDiscountRequest $request): JsonResponse
    {
        $discount = $this->discounts->create($request->validated(), $this->actorId($request));

        return response()->json([
            'success' => true,
            'message' => 'Them ma giam gia thanh cong',
            'data' => $this->resource($discount),
        ], 201);
    }

    public function show(int|string $id): JsonResponse
    {
        $discount = $this->discounts->find((int) $id);

        if (! $discount) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Lay chi tiet ma giam gia thanh cong',
            'data' => $this->resource($discount),
        ]);
    }

    public function destroy(int|string $id): JsonResponse
    {
        $result = $this->discounts->deleteOrDeactivate((int) $id);

        if (! $result) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => $result['action'] === 'deleted'
                ? 'Xoa ma giam gia thanh cong'
                : 'Ma giam gia da duoc ngung ap dung',
            'data' => $this->resource($result['discount']),
        ]);
    }

    public function deactivate(int|string $id): JsonResponse
    {
        $discount = $this->discounts->deactivate((int) $id);

        if (! $discount) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Ma giam gia da duoc ngung ap dung',
            'data' => $this->resource($discount),
        ]);
    }

    private function actorId(Request $request): int
    {
        return (int) data_get($request->attributes->get('auth_user', []), 'id', 0);
    }

    private function resource(Discount $discount): array
    {
        return (new DiscountResource($discount))->resolve();
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Khong tim thay ma giam gia',
            'errors' => (object) [],
        ], 404);
    }
}
