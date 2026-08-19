<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Services\BrandService;
use App\Services\CatalogCache;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(
        private readonly BrandService $brandService,
        private readonly CatalogCache $cache,
    ) {}

    // Lấy toàn bộ dữ liệu.
    public function index(): JsonResponse
    {
        $brands = $this->cache->remember(
            'brands:active',
            900,
            fn (): array => BrandResource::collection($this->brandService->activeBrands())->resolve(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Thành công.',
            'data' => $brands,
        ]);
    }
}
