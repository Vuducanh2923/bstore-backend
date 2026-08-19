<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly ComplaintService $complaints) {}

    // Lấy toàn bộ dữ liệu.
    public function index(Request $request): JsonResponse
    {
        $complaints = $this->complaints->paginated(
            $request->only(['page', 'limit', 'per_page', 'status']),
            $this->authenticatedActor($request),
        );

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách khiếu nại thành công',
            'data' => $this->complaints->serializeMany($complaints->items()),
            'pagination' => [
                'page' => $complaints->currentPage(),
                'limit' => $complaints->perPage(),
                'total' => $complaints->total(),
                'totalPages' => $complaints->lastPage(),
            ],
        ]);
    }

    // Lấy toàn bộ dữ liệu.
    public function show(Request $request, int|string $id): JsonResponse
    {
        $complaint = $this->complaints->find((int) $id, $this->authenticatedActor($request));

        if (! $complaint) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy khiếu nại',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lấy chi tiết khiếu nại thành công',
            'data' => $this->complaints->serialize($complaint),
        ]);
    }

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:191'],
            'content' => ['required', 'string'],
        ]);

        $complaint = $this->complaints->create($data, $this->authenticatedActor($request));

        return response()->json([
            'success' => true,
            'message' => 'Gửi khiếu nại thành công',
            'data' => $this->complaints->serialize($complaint),
        ], 201);
    }

    // Xử lý dữ liệu theo nghiệp vụ của hàm.
    public function process(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'reply' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        return $this->transitionResponse(
            $this->complaints->process((int) $id, $this->authenticatedActor($request), $data['reply'] ?? $data['note'] ?? null),
            'Nhận xử lý khiếu nại thành công',
        );
    }

    // Xây dựng hoặc chuyển đổi dữ liệu theo nghiệp vụ của hàm.
    public function resolve(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'reply' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        return $this->transitionResponse(
            $this->complaints->resolve((int) $id, $this->authenticatedActor($request), $data['reply'] ?? $data['note'] ?? null),
            'Giải quyết khiếu nại thành công',
        );
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function reject(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'reply' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        return $this->transitionResponse(
            $this->complaints->reject((int) $id, $this->authenticatedActor($request), $data['reply'] ?? $data['note'] ?? null),
            'Từ chối khiếu nại thành công',
        );
    }

    // Thực hiện chuyển trạng thái phản hồi.
    private function transitionResponse($complaint, string $message): JsonResponse
    {
        if (! $complaint) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy khiếu nại',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->complaints->serialize($complaint),
        ]);
    }

    // Thực hiện authenticated người thực hiện.
    private function authenticatedActor(Request $request): array
    {
        return (array) $request->attributes->get('auth_user', []);
    }
}
