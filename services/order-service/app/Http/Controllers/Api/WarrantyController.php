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
    public function __construct(private readonly WarrantyService $warranties) {}

    public function customerIndex(Request $request): JsonResponse
    {
        $page = $this->warranties->customerList(
            $this->actorId($request),
            $request->only(['page', 'limit', 'per_page', 'status', 'search']),
        );

        return $this->listResponse($page, false);
    }

    public function customerShow(Request $request, int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->customerDetail($this->actorId($request), (int) $id),
            'Lay chi tiet yeu cau bao hanh thanh cong',
        );
    }

    public function store(StoreWarrantyRequest $request): JsonResponse
    {
        $warranty = $this->warranties->create($this->actorId($request), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Gui yeu cau bao hanh thanh cong',
            'data' => $this->resource($warranty),
        ], 201);
    }

    public function cancel(Request $request, int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->cancel($this->actorId($request), (int) $id),
            'Huy yeu cau bao hanh thanh cong',
        );
    }

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

    public function adminShow(int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->adminDetail((int) $id),
            'Lay chi tiet yeu cau bao hanh thanh cong',
        );
    }

    public function approve(WarrantyNoteRequest $request, int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->approve((int) $id, $this->actor($request), $request->validated('processing_note')),
            'Duyet yeu cau bao hanh thanh cong',
        );
    }

    public function reject(RejectWarrantyRequest $request, int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->reject((int) $id, $this->actor($request), $request->validated('rejection_reason')),
            'Tu choi yeu cau bao hanh thanh cong',
        );
    }

    public function processing(WarrantyNoteRequest $request, int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->processing((int) $id, $request->validated('processing_note')),
            'Chuyen yeu cau sang dang bao hanh thanh cong',
        );
    }

    public function complete(WarrantyNoteRequest $request, int|string $id): JsonResponse
    {
        return $this->detailResponse(
            $this->warranties->complete((int) $id, $request->validated('processing_note')),
            'Hoan tat yeu cau bao hanh thanh cong',
        );
    }

    private function listResponse($page, bool $withCustomer): JsonResponse
    {
        $data = collect($page->items())->map(function (WarrantyRequest $warranty) use ($withCustomer): array {
            return $this->resource($this->warranties->hydrate($warranty, $withCustomer));
        })->all();

        return response()->json([
            'success' => true,
            'message' => 'Lay danh sach yeu cau bao hanh thanh cong',
            'data' => $data,
            'pagination' => [
                'page' => $page->currentPage(),
                'limit' => $page->perPage(),
                'total' => $page->total(),
                'totalPages' => $page->lastPage(),
            ],
        ]);
    }

    private function detailResponse(?WarrantyRequest $warranty, string $message): JsonResponse
    {
        if (! $warranty) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay yeu cau bao hanh',
                'errors' => (object) [],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->resource($warranty),
        ]);
    }

    private function actor(Request $request): array
    {
        return (array) $request->attributes->get('auth_user', []);
    }

    private function actorId(Request $request): int
    {
        return (int) ($this->actor($request)['id'] ?? 0);
    }

    private function resource(WarrantyRequest $warranty): array
    {
        return (new WarrantyRequestResource($warranty))->resolve();
    }
}
