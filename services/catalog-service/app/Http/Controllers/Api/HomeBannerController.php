<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HomeBannerController extends Controller
{
    private const FILE_PATH = 'app/home-banners.json';

    private const POSITIONS = [
        'hero_main',
        'hero_right_top',
        'hero_right_bottom',
    ];

    private const POSITION_SLOTS = [
        'hero_main' => 1,
        'hero_right_top' => 2,
        'hero_right_bottom' => 3,
    ];

    private const LEGACY_IMAGE_URLS = [
        'uploads/banners/flash-sale.jpg' => 'https://res.cloudinary.com/demo/image/upload/c_fill,w_1200,h_420,q_auto,f_auto/sample.jpg',
        'uploads/banners/right-top.jpg' => 'https://res.cloudinary.com/demo/image/upload/c_fill,w_600,h_250,q_auto,f_auto/docs/shoes.jpg',
        'uploads/banners/pc-gaming.jpg' => 'https://res.cloudinary.com/demo/image/upload/c_fill,w_600,h_250,q_auto,f_auto/bike.jpg',
    ];

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->activeBannersByPosition(),
        ]);
    }

    private function activeBannersByPosition(): array
    {
        $databaseBanners = $this->activeDatabaseBannersByPosition();

        if ($this->hasAnyBanner($databaseBanners)) {
            return $databaseBanners;
        }

        $banners = $this->readBannerConfig();
        $result = [];

        foreach (self::POSITIONS as $position) {
            $items = collect($banners[$position] ?? [])
                ->filter(fn ($banner) => is_array($banner))
                ->filter(fn (array $banner) => filter_var($banner['status'] ?? false, FILTER_VALIDATE_BOOLEAN))
                ->sortBy(fn (array $banner) => (int) ($banner['sort_order'] ?? 0))
                ->map(fn (array $banner) => $this->withResolvableImageUrl($banner))
                ->values()
                ->all();

            $result[$position] = $items;
        }

        return $result;
    }

    private function activeDatabaseBannersByPosition(): array
    {
        $result = $this->emptyPositionResult();

        try {
            if (! Schema::connection((new Banner)->getConnectionName())->hasTable('banners')) {
                return $result;
            }

            $hasDisplaySlot = Schema::connection((new Banner)->getConnectionName())->hasColumn('banners', 'display_slot');
            $query = Banner::query()
                ->where('status', true);

            if ($hasDisplaySlot) {
                $query->orderBy('display_slot');
            }

            $query
                ->orderBy('sort_order')
                ->orderByDesc('id');

            $query->get()
                ->map(fn (Banner $banner) => $this->databaseBannerPayload($banner, $hasDisplaySlot))
                ->each(function (array $banner) use (&$result) {
                    $position = $this->positionForSlot((int) ($banner['display_slot'] ?? 1));
                    $result[$position][] = $banner;
                });
        } catch (Throwable $exception) {
            report($exception);
        }

        return $result;
    }

    private function databaseBannerPayload(Banner $banner, bool $hasDisplaySlot): array
    {
        $payload = $banner->toArray();
        $payload['display_slot'] = $hasDisplaySlot ? (int) ($banner->display_slot ?: 1) : 1;
        $payload['link'] = $banner->route ?: $banner->button_link;

        return $payload;
    }

    private function emptyPositionResult(): array
    {
        return array_fill_keys(self::POSITIONS, []);
    }

    private function hasAnyBanner(array $banners): bool
    {
        foreach ($banners as $items) {
            if (! empty($items)) {
                return true;
            }
        }

        return false;
    }

    private function positionForSlot(int $slot): string
    {
        $positionsBySlot = array_flip(self::POSITION_SLOTS);

        return $positionsBySlot[$slot] ?? 'hero_main';
    }

    private function readBannerConfig(): array
    {
        $path = storage_path(self::FILE_PATH);

        if (! File::exists($path)) {
            $this->writeDefaultBannerConfig($path);
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function writeDefaultBannerConfig(string $path): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($this->defaultBannerConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
    }

    private function defaultBannerConfig(): array
    {
        return [
            'hero_main' => [
                [
                    'title' => 'Flash Sale 12h',
                    'image_url' => self::LEGACY_IMAGE_URLS['uploads/banners/flash-sale.jpg'],
                    'link' => '/deals',
                    'status' => true,
                    'sort_order' => 1,
                ],
            ],
            'hero_right_top' => [
                [
                    'title' => 'Siêu sale công nghệ',
                    'image_url' => self::LEGACY_IMAGE_URLS['uploads/banners/right-top.jpg'],
                    'link' => '/sale',
                    'status' => true,
                    'sort_order' => 1,
                ],
            ],
            'hero_right_bottom' => [
                [
                    'title' => 'PC gaming và linh kiện mới',
                    'image_url' => self::LEGACY_IMAGE_URLS['uploads/banners/pc-gaming.jpg'],
                    'link' => '/category/pc-gaming',
                    'status' => true,
                    'sort_order' => 1,
                ],
            ],
        ];
    }

    private function withResolvableImageUrl(array $banner): array
    {
        $banner['image_url'] = $this->resolveImageUrl($banner['image_url'] ?? null);

        return $banner;
    }

    private function resolveImageUrl(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        $imageUrl = trim($value);

        if ($imageUrl === '' || preg_match('/^(https?:)?\/\//i', $imageUrl)) {
            return $imageUrl;
        }

        $imagePath = ltrim($imageUrl, '/');

        return self::LEGACY_IMAGE_URLS[$imagePath] ?? $imageUrl;
    }
}
