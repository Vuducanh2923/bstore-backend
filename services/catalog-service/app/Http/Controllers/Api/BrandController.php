<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Services\BrandService;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function __construct(private readonly BrandService $brandService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => BrandResource::collection($this->brandService->activeBrands())->resolve(),
        ]);
    }
}
