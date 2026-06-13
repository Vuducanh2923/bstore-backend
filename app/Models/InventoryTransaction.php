<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $connection = 'bstore_catalog';

    protected $table = 'inventory_transactions';

    public $timestamps = false;

    protected $fillable = [
        'product_variant_id',
        'type',
        'quantity',
        'note',
        'created_by',
    ];

    protected $casts = [
        'product_variant_id' => 'integer',
        'quantity' => 'integer',
        'created_by' => 'integer',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
