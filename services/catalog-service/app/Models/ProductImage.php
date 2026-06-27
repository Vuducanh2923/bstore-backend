<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $connection = 'bstore_catalog';

    protected $table = 'product_images';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'image_url',
        'public_id',
        'is_thumbnail',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'is_thumbnail' => 'boolean',
    ];

    public static function resolveImageUrl(?string $value): ?string
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

        if (
            str_starts_with(strtolower($imagePath), 'storage/')
            || str_starts_with(strtolower($imagePath), 'uploads/')
        ) {
            return $appUrl.'/'.$imagePath;
        }

        return $appUrl.'/storage/'.$imagePath;
    }

    public function getImageUrlAttribute(?string $value): ?string
    {
        return self::resolveImageUrl($value);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
