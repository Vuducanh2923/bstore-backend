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
        'is_thumbnail',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'is_thumbnail' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
