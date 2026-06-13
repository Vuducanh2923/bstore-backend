<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $connection = 'bstore_order';

    protected $table = 'carts';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
