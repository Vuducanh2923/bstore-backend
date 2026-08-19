<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warranty\RejectWarrantyRequest;
use App\Http\Requests\Warranty\StoreWarrantyRequest;
use App\Http\Requests\Warranty\WarrantyNoteRequest;
use App\Http\Resources\WarrantyRequestResource;
use App\Models\WarrantyRequest;
use App\Services\WarrantyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarrantyController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly WarrantyService $warranties) {}

    // Thực hiện khách hàng chỉ mục.
    public function customerIndex(Request $request): JsonResponse
    {
        $page = $this->warranties->customerList(
            $this->actorId($request),
            $request->only(['page', 'limit', 'per_page', 'status', 'search']),
        );

        return $this->listResponse($page, false);
    }

    // Thực hiện khách hàng show.
    public function customerShow(Request $request, int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->customerDetail($this->actorId($request), (int) $id),
            'Lấy chi tiết yêu cầu bảo hành thành công',
        );
    }

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
    public function store(StoreWarrantyRequest $request): JsonResponse
    {
        $warranty = $this->warranties->create($this->actorId($request), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Gửi yêu cầu bảo hành thành công',
            'data' => $this->resource($warranty),
        ], 201);
    }

    // Xóa hoặc hủy dữ liệu theo nghiệp vụ của hàm.
    public function cancel(Request $request, int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->cancel($this->actorId($request), (int) $id),
            'Hủy yêu cầu bảo hành thành công',
        );
    }

    // Thực hiện quản trị chỉ mục.
    public function adminIndex(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,processing,completed,cancelled'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:191'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->listResponse(
            $this->warranties->adminList($request->only([
                'page', 'limit', 'per_page', 'status', 'search', 'date_from', 'date_to',
            ])),
            true,
        );
    }

    // Thực hiện quản trị show.
    public function adminShow(int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->adminDetail((int) $id),
            'Lấy chi tiết yêu cầu bảo hành thành công',
        );
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function approve(WarrantyNoteRequest $request, int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->approve((int) $id, $this->actor($request), $request->validated('processing_note')),
            'Duyệt yêu cầu bảo hành thành công',
        );
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function reject(RejectWarrantyRequest $request, int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->reject((int) $id, $this->actor($request), $request->validated('rejection_reason')),
            'Từ chối yêu cầu bảo hành thành công',
        );
    }

    // Thực hiện đang xử lý.
    public function processing(WarrantyNoteRequest $request, int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->processing((int) $id, $request->validated('processing_note')),
            'Chuyển yêu cầu sang đang bảo hành thành công',
        );
    }

    // Thực hiện complete.
    public function complete(WarrantyNoteRequest $request, int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->complete((int) $id, $request->validated('processing_note')),
            'Hoàn tất yêu cầu bảo hành thành công',
        );
    }

    // Lấy phản hồi.
    private function listResponse($page, bool $withCustomer): JsonResponse
    {
        $data = collect($page->items())->map(function (WarrantyRequest $warranty) use ($withCustomer): array {
            return $this->resource($this->warranties->hydrate($warranty, $withCustomer));
        })->all();

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách yêu cầu bảo hành thành công',
            'data' => $data,
            'pagination' => [
                'page' => $page->currentPage(),
                'limit' => $page->perPage(),
                'total' => $page->total(),
                'totalPages' => $page->lastPage(),
            ],
        ]);
    }

    // Thực hiện chi tiết phản hồi.
    private function detailResponse(?WarrantyRequest $warranty, string $message): JsonResponse
    {
        if (! $warranty) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy yêu cầu bảo hành',
                'errors' => (object) [],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->resource($warranty),
        ]);
    }

    // Thực hiện người thực hiện.
    private function actor(Request $request): array
    {
        return (array) $request->attributes->get('auth_user', []);
    }

    // Thực hiện người thực hiện id.
    private function actorId(Request $request): int
    {
        return (int) ($this->actor($request)['id'] ?? 0);
    }

    // Thực hiện tài nguyên.
    private function resource(WarrantyRequest $warranty): array
    {
        return (new WarrantyRequestResource($warranty))->resolve();
    }
}
