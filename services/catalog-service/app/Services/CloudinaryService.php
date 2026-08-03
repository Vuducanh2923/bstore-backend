<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class CloudinaryService
{
    private const BANNER_FOLDER = 'bstore/banners';

    private const BRAND_FOLDER = 'bstore/brands';

    private const PRODUCT_FOLDER = 'bstore/products';

    private ?Cloudinary $client = null;

    public function uploadBannerImage(UploadedFile $file): array
    {
        return $this->uploadImage($file, self::BANNER_FOLDER);
    }

    public function uploadBrandLogo(UploadedFile $file): array
    {
        return $this->uploadImage($file, self::BRAND_FOLDER);
    }

    public function uploadProductImage(UploadedFile $file): array
    {
        return $this->uploadImage($file, self::PRODUCT_FOLDER);
    }

    public function isConfigured(): bool
    {
        $config = config('services.cloudinary', []);

        if (trim((string) ($config['url'] ?? '')) !== '') {
            return true;
        }

        return trim((string) ($config['cloud_name'] ?? '')) !== ''
            && trim((string) ($config['api_key'] ?? '')) !== ''
            && trim((string) ($config['api_secret'] ?? '')) !== '';
    }

    public function deleteImage(?string $publicId): void
    {
        $publicId = trim((string) $publicId);

        if ($publicId === '') {
            return;
        }

        $response = $this->client()->uploadApi()->destroy($publicId, [
            'resource_type' => 'image',
            'invalidate' => true,
        ]);

        $result = (string) ($response['result'] ?? '');

        if ($result !== '' && ! in_array($result, ['ok', 'not found'], true)) {
            throw new RuntimeException("Xóa ảnh trên Cloudinary thất bại: {$result}");
        }
    }

    private function uploadImage(UploadedFile $file, string $folder): array
    {
        $path = $file->getRealPath();

        if (! $path) {
            throw new RuntimeException('Không thể đọc tệp đã tải lên.');
        }

        $response = $this->client()->uploadApi()->upload($path, [
            'folder' => $folder,
            'resource_type' => 'image',
            'use_filename' => true,
            'unique_filename' => true,
            'overwrite' => false,
        ]);

        $secureUrl = (string) ($response['secure_url'] ?? '');
        $publicId = (string) ($response['public_id'] ?? '');

        if ($secureUrl === '' || $publicId === '') {
            throw new RuntimeException('Phản hồi tải ảnh của Cloudinary thiếu secure_url hoặc public_id.');
        }

        return [
            'secure_url' => $secureUrl,
            'public_id' => $publicId,
        ];
    }

    private function client(): Cloudinary
    {
        if ($this->client) {
            return $this->client;
        }

        $config = config('services.cloudinary', []);
        $cloudinaryUrl = trim((string) ($config['url'] ?? ''));

        if ($cloudinaryUrl !== '') {
            return $this->client = new Cloudinary($cloudinaryUrl);
        }

        $cloudName = trim((string) ($config['cloud_name'] ?? ''));
        $apiKey = trim((string) ($config['api_key'] ?? ''));
        $apiSecret = trim((string) ($config['api_secret'] ?? ''));

        if ($cloudName === '' || $apiKey === '' || $apiSecret === '') {
            throw new RuntimeException('Thông tin xác thực Cloudinary chưa được cấu hình.');
        }

        return $this->client = new Cloudinary([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }
}
