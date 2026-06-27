<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->select(['id', 'name', 'slug', 'status'])
            ->where('status', 'active')
            ->orderBy('name')
            ->paginate($this->perPage($request), ['*'], 'page', $this->page($request));

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $categories->items(),
            'pagination' => [
                'page' => $categories->currentPage(),
                'limit' => $categories->perPage(),
                'total' => $categories->total(),
                'totalPages' => $categories->lastPage(),
            ],
        ]);
    }

    private function perPage(Request $request): int
    {
        return min(
            self::MAX_PER_PAGE,
            max(1, (int) ($request->query('limit', $request->query('per_page', self::DEFAULT_PER_PAGE))))
        );
    }

    private function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }
}
