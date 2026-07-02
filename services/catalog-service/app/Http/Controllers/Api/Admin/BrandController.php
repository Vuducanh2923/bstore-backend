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
    public function __construct(
        private readonly BrandService $brandService,
        private readonly CatalogCache $cache,
    ) {}

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
            'message' => 'Success',
            'data' => BrandResource::collection($brands->items())->resolve(),
            'pagination' => [
                'page' => $brands->currentPage(),
                'limit' => $brands->perPage(),
                'total' => $brands->total(),
                'totalPages' => $brands->lastPage(),
            ],
        ]);
    }

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

    private function logoFile(Request $request): mixed
    {
        return $request->file('logo') ?: $request->file('logo_file');
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Không tìm thấy nhãn hàng',
        ], 404);
    }

    private function uploadError(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Upload logo thất bại',
        ], 502);
    }
}
