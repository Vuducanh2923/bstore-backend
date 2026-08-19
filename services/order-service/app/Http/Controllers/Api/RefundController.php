<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly RefundService $refunds) {}

    // Lấy toàn bộ dữ liệu.
    public function index(Request $request): JsonResponse
    {
        $refunds = $this->refunds->paginated(
            $request->only(['page', 'limit', 'per_page', 'status']),
            $this->authenticatedActor($request),
        );

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách yêu cầu hoàn tiền thành công',
            'data' => $this->refunds->serializeMany($refunds->items()),
            'pagination' => [
                'page' => $refunds->currentPage(),
                'limit' => $refunds->perPage(),
                'total' => $refunds->total(),
                'totalPages' => $refunds->lastPage(),
            ],
        ]);
    }

    // Lấy toàn bộ dữ liệu.
    public function show(Request $request, int|string $id): JsonResponse
    {
        $refund = $this->refunds->find((int) $id, $this->authenticatedActor($request));

        if (! $refund) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy yêu cầu hoàn tiền',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lấy chi tiết yêu cầu hoàn tiền thành công',
            'data' => $this->refunds->serialize($refund),
        ]);
    }

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'reason' => ['required', 'string'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $refund = $this->refunds->create($data, $this->authenticatedActor($request));

        return response()->json([
            'success' => true,
            'message' => 'Gửi yêu cầu hoàn tiền thành công',
            'data' => $this->refunds->serialize($refund),
        ], 201);
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function approve(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        return $this->transitionResponse(
            $this->refunds->approve((int) $id, $this->authenticatedActor($request), $data['admin_note'] ?? $data['note'] ?? null),
            'Duyệt yêu cầu hoàn tiền thành công',
        );
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function reject(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        return $this->transitionResponse(
            $this->refunds->reject((int) $id, $this->authenticatedActor($request), $data['admin_note'] ?? $data['note'] ?? null),
            'Từ chối yêu cầu hoàn tiền thành công',
        );
    }

    // Thực hiện refunding.
    public function refunding(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        return $this->transitionResponse(
            $this->refunds->markRefunding((int) $id, $this->authenticatedActor($request), $data['admin_note'] ?? $data['note'] ?? null),
            'Cập nhật yêu cầu hoàn tiền đang xử lý thành công',
        );
    }

    // Thực hiện completed.
    public function completed(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'refund_method' => ['required', 'string', 'max:50'],
            'refund_transaction' => ['nullable', 'string', 'max:191'],
            'admin_note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        if (! empty($data['note']) && empty($data['admin_note'])) {
            $data['admin_note'] = $data['note'];
        }

        return $this->transitionResponse(
            $this->refunds->complete((int) $id, $this->authenticatedActor($request), $data),
            'Hoàn tất yêu cầu hoàn tiền thành công',
        );
    }

    // Thực hiện chuyển trạng thái phản hồi.
    private function transitionResponse($refund, string $message): JsonResponse
    {
        if (! $refund) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy yêu cầu hoàn tiền',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->refunds->serialize($refund),
        ]);
    }

    // Thực hiện authenticated người thực hiện.
    private function authenticatedActor(Request $request): array
    {
        return (array) $request->attributes->get('auth_user', []);
    }
}
