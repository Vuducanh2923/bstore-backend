<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $connection = 'bstore_order';

    protected $table = 'cart_items';

    public $timestamps = false;

    protected $fillable = [
        'cart_id',
        'product_variant_id',
        'product_name',
        'color',
        'ram',
        'storage',
        'price',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'cart_id' => 'integer',
        'product_variant_id' => 'integer',
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'subtotal' => 'decimal:2',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }
}
