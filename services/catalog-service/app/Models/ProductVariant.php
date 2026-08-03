<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $appends = [
        'quantity',
        'reserved_quantity',
        'available_quantity',
    ];

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

    public function inventoryReservations()
    {
        return $this->hasMany(InventoryReservation::class, 'product_variant_id');
    }

    public function getQuantityAttribute(): int
    {
        return (int) ($this->relationLoaded('inventory') ? $this->inventory?->quantity : 0);
    }

    public function getReservedQuantityAttribute(): int
    {
        return (int) ($this->relationLoaded('inventory') ? $this->inventory?->reserved_quantity : 0);
    }

    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity - $this->reserved_quantity;
    }
}
