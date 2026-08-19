<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandStoreRequest;
use App\Http\Requests\Admin\BrandUpdateRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\BrandService;
use App\Services\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BrandController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(
        private readonly BrandService $brandService,
        private readonly CatalogCache $cache,
    ) {}

    // Lấy toàn bộ dữ liệu.
    public function index(Request $request): JsonResponse
    {
        $brands = $this->brandService->adminPaginatedList($request->only([
            'page',
            'limit',
            'per_page',
            'search',
            'keyword',
            'status',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Thành công.',
            'data' => BrandResource::collection($brands->items())->resolve(),
            'pagination' => [
                'page' => $brands->currentPage(),
                'limit' => $brands->perPage(),
                'total' => $brands->total(),
                'totalPages' => $brands->lastPage(),
            ],
        ]);
    }

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
    public function store(BrandStoreRequest $request): JsonResponse
    {
        try {
            $brand = $this->brandService->create($request->validated(), $this->logoFile($request));
            $this->cache->bump();
        } catch (Throwable $exception) {
            report($exception);

            return $this->uploadError();
        }

        return response()->json([
            'success' => true,
            'message' => 'Tạo nhãn hàng thành công',
            'data' => BrandResource::make($brand),
        ], 201);
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function update(BrandUpdateRequest $request, int $id): JsonResponse
    {
        $brand = Brand::find($id);

        if (! $brand) {
            return $this->notFound();
        }

        try {
            $brand = $this->brandService->update($brand, $request->validated(), $this->logoFile($request));
            $this->cache->bump();
        } catch (Throwable $exception) {
            report($exception);

            return $this->uploadError();
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật nhãn hàng thành công',
            'data' => BrandResource::make($brand),
        ]);
    }

    // Xóa hoặc hủy dữ liệu theo nghiệp vụ của hàm.
    public function destroy(int $id): JsonResponse
    {
        $brand = Brand::find($id);

        if (! $brand) {
            return $this->notFound();
        }

        if (! $this->brandService->delete($brand)) {
            return response()->json([
                'success' => false,
                'message' => 'Nhãn hàng đang được sử dụng.',
            ], 409);
        }

        $this->cache->bump();

        return response()->json([
            'success' => true,
            'message' => 'Xóa nhãn hàng thành công',
        ]);
    }

    // Thực hiện toggle trạng thái.
    public function toggleStatus(int $id): JsonResponse
    {
        $brand = Brand::find($id);

        if (! $brand) {
            return $this->notFound();
        }

        $brand = $this->brandService->toggleStatus($brand);
        $this->cache->bump();

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật trạng thái nhãn hàng thành công',
            'data' => BrandResource::make($brand),
        ]);
    }

    // Thực hiện logo tệp.
    private function logoFile(Request $request): mixed
    {
        return $request->file('logo') ?: $request->file('logo_file');
    }

    // Thực hiện not found.
    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Không tìm thấy nhãn hàng',
        ], 404);
    }

    // Tải hoặc xuất lỗi.
    private function uploadError(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Tải logo thất bại',
        ], 502);
    }
}
