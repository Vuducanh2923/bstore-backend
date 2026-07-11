<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderHistory extends Model
{
    protected $connection = 'bstore_order';

    protected $table = 'order_histories';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'action',
        'old_status',
        'new_status',
        'staff_id',
        'staff_name',
        'note',
        'created_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'staff_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
