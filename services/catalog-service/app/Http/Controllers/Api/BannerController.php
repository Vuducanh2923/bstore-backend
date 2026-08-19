<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\CatalogCache;
use App\Services\CloudinaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BannerController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(
        private readonly CloudinaryService $cloudinaryService,
        private readonly CatalogCache $cache,
    ) {}

    // Lấy toàn bộ dữ liệu.
    public function index(Request $request): JsonResponse
    {
        $banners = $this->cache->remember(
            'banners:index:'.md5(json_encode($request->query())),
            600,
            function () use ($request) {
                $query = Banner::query();

                if ($request->has('status')) {
                    $status = filter_var($request->query('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                    if ($status !== null) {
                        $query->where('status', $status);
                    }
                }

                return $this->orderedBannerQuery($query)->get();
            }
        );

        return response()->json([
            'success' => true,
            'data' => $banners,
        ]);
    }

    // Thực hiện ordered banner query.
    private function orderedBannerQuery(Builder $query): Builder
    {
        if ($this->bannerTableHasColumn('display_slot')) {
            $query->orderBy('display_slot');
        }

        return $query
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    // Lấy toàn bộ dữ liệu.
    public function show(int $id): JsonResponse
    {
        $banner = Banner::find($id);

        if (! $banner) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy banner',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $banner,
        ]);
    }

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request, true);

        try {
            $data = $this->withImageData($request, $data);
        } catch (Throwable $exception) {
            report($exception);

            return $this->cloudinaryError('Tải ảnh lên Cloudinary thất bại');
        }

        $data = $this->onlyExistingBannerColumns($data);
        $banner = Banner::create($data);
        $this->cache->bump();

        return response()->json([
            'success' => true,
            'message' => 'Tạo banner thành công',
            'data' => $banner->fresh(),
        ], 201);
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function update(Request $request, int $id): JsonResponse
    {
        $banner = Banner::find($id);

        if (! $banner) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy banner',
            ], 404);
        }

        $oldPublicId = $banner->public_id;
        $data = $this->validatedData($request, false);

        try {
            $data = $this->withImageData($request, $data, $banner);
        } catch (Throwable $exception) {
            report($exception);

            return $this->cloudinaryError('Tải ảnh lên Cloudinary thất bại');
        }

        $data = $this->onlyExistingBannerColumns($data);
        $banner->fill($data);
        $banner->save();
        $this->cache->bump();

        if (array_key_exists('public_id', $data) && $oldPublicId && $oldPublicId !== $banner->public_id) {
            try {
                $this->cloudinaryService->deleteImage($oldPublicId);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật banner thành công',
            'data' => $banner->fresh(),
        ]);
    }

    // Xóa hoặc hủy dữ liệu theo nghiệp vụ của hàm.
    public function destroy(int $id): JsonResponse
    {
        $banner = Banner::find($id);

        if (! $banner) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy banner',
            ], 404);
        }

        if ($banner->public_id) {
            try {
                $this->cloudinaryService->deleteImage($banner->public_id);
            } catch (Throwable $exception) {
                report($exception);

                return $this->cloudinaryError('Xóa ảnh trên Cloudinary thất bại');
            }
        }

        $banner->delete();
        $this->cache->bump();

        return response()->json([
            'success' => true,
            'message' => 'óa banner thành công',
        ]);
    }

    // Thực hiện validated dữ liệu.
    private function validatedData(Request $request, bool $creating): array
    {
        $this->normalizeBooleanInput($request, 'status');

        $optional = $creating ? ['nullable'] : ['sometimes', 'nullable'];
        $imageRequired = $creating ? 'required_without:image_url' : 'sometimes';
        $urlRequired = $creating ? 'required_without:image' : 'sometimes';

        $data = $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'subtitle' => [...$optional, 'string', 'max:255'],
            'description' => [...$optional, 'string'],
            'button_text' => [...$optional, 'string', 'max:100'],
            'button_link' => [...$optional, 'string', 'max:500'],
            'image' => [$imageRequired, 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_url' => [$urlRequired, 'exclude_with:image', 'url', 'max:500'],
            'route' => [...$optional, 'string', 'max:255'],
            'display_slot' => [$creating ? 'nullable' : 'sometimes', 'integer', 'in:1,2,3'],
            'sort_order' => [$creating ? 'nullable' : 'sometimes', 'integer', 'min:0'],
            'status' => [$creating ? 'nullable' : 'sometimes', 'boolean'],
        ]);

        return $this->onlyExistingBannerColumns($data);
    }

    // Thực hiện banner bảng has cột.
    private function bannerTableHasColumn(string $column): bool
    {
        return Schema::connection((new Banner)->getConnectionName())->hasColumn('banners', $column);
    }

    // Thực hiện only existing banner columns.
    private function onlyExistingBannerColumns(array $data): array
    {
        $columns = Schema::connection((new Banner)->getConnectionName())->getColumnListing('banners');

        return array_intersect_key($data, array_flip($columns));
    }

    // Chuẩn hóa boolean input.
    private function normalizeBooleanInput(Request $request, string $key): void
    {
        $value = $request->input($key);

        if (! is_string($value)) {
            return;
        }

        $normalized = strtolower(trim($value));

        if ($normalized === 'true') {
            $request->merge([$key => '1']);
        }

        if ($normalized === 'false') {
            $request->merge([$key => '0']);
        }
    }

    // Thực hiện cùng hình ảnh dữ liệu.
    private function withImageData(Request $request, array $data, ?Banner $banner = null): array
    {
        unset($data['image']);

        if ($request->hasFile('image')) {
            return [
                ...$data,
                ...$this->uploadBannerImage($request),
            ];
        }

        if (array_key_exists('image_url', $data)) {
            $data['image_url'] = trim((string) $data['image_url']);

            if (! $banner || $data['image_url'] !== $banner->getRawOriginal('image_url')) {
                $data['public_id'] = null;
            }
        }

        return $data;
    }

    // Tải hoặc xuất banner hình ảnh.
    private function uploadBannerImage(Request $request): array
    {
        $uploadedImage = $this->cloudinaryService->uploadBannerImage($request->file('image'));

        return [
            'image_url' => $uploadedImage['secure_url'],
            'public_id' => $uploadedImage['public_id'],
        ];
    }

    // Thực hiện cloudinary lỗi.
    private function cloudinaryError(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 502);
    }
}
