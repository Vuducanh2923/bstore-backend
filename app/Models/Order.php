<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $connection = 'bstore_order';

    protected $table = 'orders';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'order_code',
        'receiver_name',
        'receiver_phone',
        'receiver_email',
        'shipping_address',
        'shipping_method',
        'total_amount',
        'discount_amount',
        'final_amount',
        'status',
        'payment_status',
        'cancel_reason',
        'note',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function discounts()
    {
        return $this->hasMany(OrderDiscount::class);
    }
}
