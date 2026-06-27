<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandService
{
    private const DEFAULT_PER_PAGE = 10;

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly CloudinaryService $cloudinaryService) {}

    public function adminPaginatedList(array $filters = []): LengthAwarePaginator
    {
        $query = Brand::query()
            ->withCount('products')
            ->orderByDesc('id');

        $search = trim((string) ($filters['search'] ?? $filters['keyword'] ?? ''));

        if ($search !== '') {
            $query->where(function ($brandQuery) use ($search) {
                $brandQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = min(
            self::MAX_PER_PAGE,
            max(1, (int) ($filters['limit'] ?? $filters['per_page'] ?? self::DEFAULT_PER_PAGE))
        );
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function activeBrands(): Collection
    {
        return Brand::query()
            ->select(['id', 'name', 'slug', 'logo', 'description', 'status'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data, ?UploadedFile $logoFile = null): Brand
    {
        $payload = $this->payload($data, true, null, $logoFile);

        return Brand::create($payload)->fresh() ?? Brand::query()->where('slug', $payload['slug'])->firstOrFail();
    }

    public function update(Brand $brand, array $data, ?UploadedFile $logoFile = null): Brand
    {
        $brand->fill($this->payload($data, false, $brand, $logoFile));
        $brand->save();

        return $brand->fresh() ?? $brand;
    }

    public function toggleStatus(Brand $brand): Brand
    {
        $brand->status = $brand->status === 'active' ? 'inactive' : 'active';
        $brand->save();

        return $brand->fresh() ?? $brand;
    }

    public function delete(Brand $brand): bool
    {
        if ($brand->products()->exists()) {
            return false;
        }

        return (bool) $brand->delete();
    }

    private function payload(array $data, bool $creating, ?Brand $brand, ?UploadedFile $logoFile): array
    {
        $payload = [];

        foreach (['name', 'description', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if ($creating && ! array_key_exists('status', $payload)) {
            $payload['status'] = 'active';
        }

        if ($creating || array_key_exists('slug', $data)) {
            $slugSource = trim((string) ($data['slug'] ?? '')) ?: (string) ($data['name'] ?? $brand?->name ?? 'brand');
            $payload['slug'] = $this->uniqueSlug($slugSource, $brand?->id);
        }

        if ($logoFile) {
            $payload['logo'] = $this->storeLogoFile($logoFile);
        } elseif (array_key_exists('logo_url', $data)) {
            $payload['logo'] = $this->nullableTrim($data['logo_url']);
        } elseif (array_key_exists('logo', $data)) {
            $payload['logo'] = $this->nullableTrim($data['logo']);
        }

        return $payload;
    }

    private function storeLogoFile(UploadedFile $file): string
    {
        if ($this->cloudinaryService->isConfigured()) {
            return $this->cloudinaryService->uploadBrandLogo($file)['secure_url'];
        }

        $path = $file->store('brands', 'public');

        return Storage::disk('public')->url($path);
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($source) ?: 'brand';
        $baseSlug = Str::limit($baseSlug, 191, '');
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $suffixText = '-'.$suffix;
            $slug = Str::limit($baseSlug, 191 - strlen($suffixText), '').$suffixText;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId): bool
    {
        return Brand::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
