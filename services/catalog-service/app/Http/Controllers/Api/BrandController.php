<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Services\BrandService;
use App\Services\CatalogCache;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brandService,
        private readonly CatalogCache $cache,
    ) {}

    public function index(): JsonResponse
    {
        $brands = $this->cache->remember(
            'brands:active',
            900,
            fn (): array => BrandResource::collection($this->brandService->activeBrands())->resolve(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $brands,
        ]);
    }
}
