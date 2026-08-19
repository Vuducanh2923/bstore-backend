<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CategoryController extends Controller
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly CatalogCache $cache) {}

    // Lấy toàn bộ dữ liệu.
    public function index(Request $request): JsonResponse
    {
        $payload = $this->cache->remember(
            'categories:index:'.md5(json_encode($request->query())),
            600,
            function () use ($request): array {
                $categoryColumns = $this->categoryColumns();
                $categories = Category::query()
                    ->select($categoryColumns)
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->paginate($this->perPage($request), ['*'], 'page', $this->page($request));

                $items = collect($categories->items());
                $includeBrands = $this->includeBrands($request);
                $brandsByCategory = $includeBrands
                    ? $this->brandsByCategory($items->pluck('id'))
                    : collect();

                return [
                    'data' => $items
                        ->map(function (Category $category) use ($brandsByCategory, $includeBrands): array {
                            $payload = $category->toArray();

                            if ($includeBrands) {
                                $payload['brands'] = $brandsByCategory
                                    ->get($category->getKey(), collect())
                                    ->values()
                                    ->all();
                            }

                            return $payload;
                        })
                        ->all(),
                    'pagination' => [
                        'page' => $categories->currentPage(),
                        'limit' => $categories->perPage(),
                        'total' => $categories->total(),
                        'totalPages' => $categories->lastPage(),
                    ],
                ];
            },
        );

        return response()->json([
            'success' => true,
            'message' => 'Thành công.',
            ...$payload,
        ]);
    }

    // Chọn các cột danh mục tương thích với cả schema cũ.
    private function categoryColumns(): array
    {
        $schema = Schema::connection('bstore_catalog');
        $columns = ['id', 'name', 'slug', 'status'];

        foreach (['icon', 'icon_url'] as $column) {
            if ($schema->hasColumn('categories', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    // Kiểm tra yêu cầu kèm thương hiệu.
    private function includeBrands(Request $request): bool
    {
        $include = strtolower((string) ($request->query('include') ?: $request->query('with')));
        $requested = preg_split('/[\s,]+/', $include, -1, PREG_SPLIT_NO_EMPTY);

        return in_array('brands', $requested, true) || filter_var(
            $request->query('include_brands'),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    // Lấy thương hiệu theo danh mục bằng một truy vấn duy nhất.
    private function brandsByCategory(Collection $categoryIds): Collection
    {
        if ($categoryIds->isEmpty()) {
            return collect();
        }

        $brandColumns = Schema::connection('bstore_catalog')->hasColumn('brands', 'logo')
            ? ['brands.logo']
            : [DB::raw('NULL AS logo')];

        return DB::connection('bstore_catalog')
            ->table('products')
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->whereIn('products.category_id', $categoryIds->all())
            ->where('products.status', 'active')
            ->where('brands.status', 'active')
            ->select([
                'products.category_id',
                'brands.id',
                'brands.name',
                'brands.slug',
                ...$brandColumns,
            ])
            ->distinct()
            ->orderBy('brands.name')
            ->get()
            ->groupBy('category_id')
            ->map(fn (Collection $brands): Collection => $brands->map(
                fn (object $brand): array => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'logo' => $brand->logo,
                ],
            ));
    }

    // Thực hiện per trang.
    private function perPage(Request $request): int
    {
        return min(
            self::MAX_PER_PAGE,
            max(1, (int) ($request->query('limit', $request->query('per_page', self::DEFAULT_PER_PAGE))))
        );
    }

    // Thực hiện trang.
    private function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }
}
