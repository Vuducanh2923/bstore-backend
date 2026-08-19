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

    // Thực hiện sản phẩm.
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Thực hiện hình ảnh.
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    // Thực hiện tồn kho.
    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }

    // Thực hiện tồn kho reservations.
    public function inventoryReservations()
    {
        return $this->hasMany(InventoryReservation::class, 'product_variant_id');
    }

    // Lấy số lượng thuộc tính.
    public function getQuantityAttribute(): int
    {
        return (int) ($this->relationLoaded('inventory') ? $this->inventory?->quantity : 0);
    }

    // Lấy reserved số lượng thuộc tính.
    public function getReservedQuantityAttribute(): int
    {
        return (int) ($this->relationLoaded('inventory') ? $this->inventory?->reserved_quantity : 0);
    }

    // Lấy available số lượng thuộc tính.
    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity - $this->reserved_quantity;
    }
}
