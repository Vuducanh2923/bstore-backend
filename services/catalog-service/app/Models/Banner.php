<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    private const LEGACY_IMAGE_URLS = [
        'uploads/banners/flash-sale.jpg' => 'https://res.cloudinary.com/demo/image/upload/c_fill,w_1200,h_420,q_auto,f_auto/sample.jpg',
        'uploads/banners/right-top.jpg' => 'https://res.cloudinary.com/demo/image/upload/c_fill,w_600,h_250,q_auto,f_auto/docs/shoes.jpg',
        'uploads/banners/pc-gaming.jpg' => 'https://res.cloudinary.com/demo/image/upload/c_fill,w_600,h_250,q_auto,f_auto/bike.jpg',
    ];

    protected $connection = 'bstore_catalog';

    protected $table = 'banners';

    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'button_text',
        'button_link',
        'image_url',
        'public_id',
        'route',
        'display_slot',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'display_slot' => 'integer',
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    public function getImageUrlAttribute(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        $imageUrl = trim($value);

        if ($imageUrl === '' || preg_match('/^(https?:)?\/\//i', $imageUrl)) {
            return $imageUrl;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $imagePath = ltrim($imageUrl, '/');

        if (isset(self::LEGACY_IMAGE_URLS[$imagePath])) {
            return self::LEGACY_IMAGE_URLS[$imagePath];
        }

        if (
            str_starts_with(strtolower($imagePath), 'storage/')
            || str_starts_with(strtolower($imagePath), 'uploads/')
        ) {
            return $appUrl.'/'.$imagePath;
        }

        return $appUrl.'/storage/'.$imagePath;
    }
}
