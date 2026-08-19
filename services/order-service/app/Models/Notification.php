<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $connection = 'bstore_order';

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'order_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'order_id' => 'integer',
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    // Thực hiện đơn hàng.
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
