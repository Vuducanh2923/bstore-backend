<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\CloudinaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BannerController extends Controller
{
    public function __construct(private readonly CloudinaryService $cloudinaryService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Banner::query();

        if ($request->has('status')) {
            $status = filter_var($request->query('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $this->orderedBannerQuery($query)->get(),
        ]);
    }

    private function orderedBannerQuery(Builder $query): Builder
    {
        if ($this->bannerTableHasColumn('display_slot')) {
            $query->orderBy('display_slot');
        }

        return $query
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    public function show(int $id): JsonResponse
    {
        $banner = Banner::find($id);

        if (! $banner) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay banner',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $banner,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request, true);

        try {
            $data = $this->withImageData($request, $data);
        } catch (Throwable $exception) {
            report($exception);

            return $this->cloudinaryError('Upload anh len Cloudinary that bai');
        }

        $data = $this->onlyExistingBannerColumns($data);
        $banner = Banner::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tao banner thanh cong',
            'data' => $banner->fresh(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $banner = Banner::find($id);

        if (! $banner) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay banner',
            ], 404);
        }

        $oldPublicId = $banner->public_id;
        $data = $this->validatedData($request, false);

        try {
            $data = $this->withImageData($request, $data, $banner);
        } catch (Throwable $exception) {
            report($exception);

            return $this->cloudinaryError('Upload anh len Cloudinary that bai');
        }

        $data = $this->onlyExistingBannerColumns($data);
        $banner->fill($data);
        $banner->save();

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

        return response()->json([
            'success' => true,
            'message' => 'óa banner thành công',
        ]);
    }

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

    private function bannerTableHasColumn(string $column): bool
    {
        return Schema::connection((new Banner)->getConnectionName())->hasColumn('banners', $column);
    }

    private function onlyExistingBannerColumns(array $data): array
    {
        $columns = Schema::connection((new Banner)->getConnectionName())->getColumnListing('banners');

        return array_intersect_key($data, array_flip($columns));
    }

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

    private function uploadBannerImage(Request $request): array
    {
        $uploadedImage = $this->cloudinaryService->uploadBannerImage($request->file('image'));

        return [
            'image_url' => $uploadedImage['secure_url'],
            'public_id' => $uploadedImage['public_id'],
        ];
    }

    private function cloudinaryError(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 502);
    }
}
