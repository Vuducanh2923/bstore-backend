<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDiscount extends Model
{
    protected $connection = 'bstore_order';

    protected $table = 'order_discounts';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'discount_id',
        'discount_code',
        'discount_amount',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'discount_id' => 'integer',
        'discount_amount' => 'decimal:2',
    ];

    // Thực hiện đơn hàng.
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Thực hiện mã giảm giá.
    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }
}
