<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $connection = 'bstore_catalog';

    protected $table = 'product_variants';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'color',
        'ram',
        'storage',
        'specifications',
        'price',
        'sku',
        'barcode',
        'status',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'specifications' => 'array',
        'price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }
}
