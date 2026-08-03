<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Product extends Model
{
    private static array $timestampSupport = [];

    protected $connection = 'bstore_catalog';

    protected $table = 'products';

    public $timestamps = false;

    protected $appends = [
        'total_quantity',
        'total_reserved',
        'available_quantity',
        'in_stock',
    ];

    protected $fillable = [
        'category_id',
        'brand_id',
        'warranty_policy_id',
        'name',
        'slug',
        'description',
        'specifications',
        'price',
        'sale_percent',
        'discount_percent',
        'sale_price',
        'is_sale',
        'status',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'brand_id' => 'integer',
        'warranty_policy_id' => 'integer',
        'specifications' => 'array',
        'price' => 'decimal:2',
        'sale_percent' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'is_sale' => 'boolean',
    ];

    public function usesTimestamps(): bool
    {
        $cacheKey = spl_object_id($this->getConnection()).':'.$this->getTable();

        return self::$timestampSupport[$cacheKey] ??= (
            Schema::connection($this->getConnectionName())->hasColumn($this->getTable(), static::CREATED_AT)
            && Schema::connection($this->getConnectionName())->hasColumn($this->getTable(), static::UPDATED_AT)
        );
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            $product->slug = static::uniqueSlugForName($product->name);
        });

        static::updating(function (Product $product): void {
            if ($product->isDirty('name')) {
                $product->slug = static::uniqueSlugForName($product->name, $product->getKey());
            }
        });
    }

    public static function uniqueSlugForName(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'product';
        $baseSlug = Str::limit($baseSlug, 191, '');
        $slug = $baseSlug;
        $suffix = 2;

        while (static::slugExists($slug, $ignoreId)) {
            $suffixText = '-'.$suffix;
            $slug = Str::limit($baseSlug, 191 - strlen($suffixText), '').$suffixText;
            $suffix++;
        }

        return $slug;
    }

    private static function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return static::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function getThumbnailAttribute(?string $value): ?string
    {
        return ProductImage::resolveImageUrl($value);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function inventories()
    {
        return $this->hasManyThrough(
            Inventory::class,
            ProductVariant::class,
            'product_id',
            'product_variant_id',
        );
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function warrantyPolicy()
    {
        return $this->belongsTo(WarrantyPolicy::class);
    }

    public function getTotalQuantityAttribute(): int
    {
        if (array_key_exists('total_quantity', $this->attributes)) {
            return (int) $this->attributes['total_quantity'];
        }

        return $this->relationLoaded('variants')
            ? (int) $this->variants->sum(fn (ProductVariant $variant): int => $variant->quantity)
            : 0;
    }

    public function getTotalReservedAttribute(): int
    {
        if (array_key_exists('total_reserved', $this->attributes)) {
            return (int) $this->attributes['total_reserved'];
        }

        return $this->relationLoaded('variants')
            ? (int) $this->variants->sum(fn (ProductVariant $variant): int => $variant->reserved_quantity)
            : 0;
    }

    public function getAvailableQuantityAttribute(): int
    {
        return $this->total_quantity - $this->total_reserved;
    }

    public function getInStockAttribute(): bool
    {
        return $this->available_quantity > 0;
    }
}
