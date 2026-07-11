<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function __construct(private readonly RefundService $refunds) {}

    public function index(Request $request): JsonResponse
    {
        $refunds = $this->refunds->paginated(
            $request->only(['page', 'limit', 'per_page', 'status']),
            $this->authenticatedActor($request),
        );

        return response()->json([
            'success' => true,
            'message' => 'Lay danh sach yeu cau hoan tien thanh cong',
            'data' => $this->refunds->serializeMany($refunds->items()),
            'pagination' => [
                'page' => $refunds->currentPage(),
                'limit' => $refunds->perPage(),
                'total' => $refunds->total(),
                'totalPages' => $refunds->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $refund = $this->refunds->find((int) $id, $this->authenticatedActor($request));

        if (! $refund) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay yeu cau hoan tien',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lay chi tiet yeu cau hoan tien thanh cong',
            'data' => $this->refunds->serialize($refund),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'reason' => ['required', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $refund = $this->refunds->create($data, $this->authenticatedActor($request));

        return response()->json([
            'success' => true,
            'message' => 'Gui yeu cau hoan tien thanh cong',
            'data' => $this->refunds->serialize($refund),
        ], 201);
    }

    public function approve(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        return $this->transitionResponse(
            $this->refunds->approve((int) $id, $this->authenticatedActor($request), $data['admin_note'] ?? $data['note'] ?? null),
            'Duyet yeu cau hoan tien thanh cong',
        );
    }

    public function reject(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        return $this->transitionResponse(
            $this->refunds->reject((int) $id, $this->authenticatedActor($request), $data['admin_note'] ?? $data['note'] ?? null),
            'Tu choi yeu cau hoan tien thanh cong',
        );
    }

    public function refunding(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        return $this->transitionResponse(
            $this->refunds->markRefunding((int) $id, $this->authenticatedActor($request), $data['admin_note'] ?? $data['note'] ?? null),
            'Cap nhat yeu cau hoan tien dang xu ly thanh cong',
        );
    }

    public function completed(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'refund_method' => ['nullable', 'string', 'max:50'],
            'refund_transaction' => ['nullable', 'string', 'max:191'],
            'admin_note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        if (! empty($data['note']) && empty($data['admin_note'])) {
            $data['admin_note'] = $data['note'];
        }

        return $this->transitionResponse(
            $this->refunds->complete((int) $id, $this->authenticatedActor($request), $data),
            'Hoan tat yeu cau hoan tien thanh cong',
        );
    }

    private function transitionResponse($refund, string $message): JsonResponse
    {
        if (! $refund) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay yeu cau hoan tien',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->refunds->serialize($refund),
        ]);
    }

    private function authenticatedActor(Request $request): array
    {
        return (array) $request->attributes->get('auth_user', []);
    }
}
