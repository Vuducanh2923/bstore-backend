<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarrantyRequest extends Model
{
    protected $connection = 'bstore_order';

    protected $table = 'warranty_requests';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'order_id',
        'order_item_id',
        'type',
        'reason',
        'image_url',
        'status',
        'admin_note',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'order_id' => 'integer',
        'order_item_id' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
