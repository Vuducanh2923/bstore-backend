<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $connection = 'bstore_catalog';

    protected $table = 'products';

    public $timestamps = false;

    protected $fillable = [
        'category_id',
        'brand_id',
        'warranty_policy_id',
        'name',
        'slug',
        'description',
        'specifications',
        'price',
        'status',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'brand_id' => 'integer',
        'warranty_policy_id' => 'integer',
        'specifications' => 'array',
        'price' => 'decimal:2',
    ];

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

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function warrantyPolicy()
    {
        return $this->belongsTo(WarrantyPolicy::class);
    }
}
