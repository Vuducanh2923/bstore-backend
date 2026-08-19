<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryReservation extends Model
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_RELEASED = 'released';

    public const STATUS_RESTORED = 'restored';

    protected $connection = 'bstore_catalog';

    protected $fillable = [
        'reference',
        'product_variant_id',
        'quantity',
        'status',
    ];

    protected $casts = [
        'product_variant_id' => 'integer',
        'quantity' => 'integer',
    ];

    // Thực hiện biến thể.
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
