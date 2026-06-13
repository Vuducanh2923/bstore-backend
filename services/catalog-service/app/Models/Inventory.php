<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $connection = 'bstore_catalog';

    protected $table = 'inventories';

    public $timestamps = false;

    protected $fillable = [
        'product_variant_id',
        'quantity',
        'reserved_quantity',
    ];

    protected $casts = [
        'product_variant_id' => 'integer',
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
