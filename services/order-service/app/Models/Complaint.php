<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_RESOLVED,
        self::STATUS_REJECTED,
    ];

    protected $connection = 'bstore_order';

    protected $table = 'complaints';

    protected $fillable = [
        'order_id',
        'customer_id',
        'staff_id',
        'staff_name',
        'staff_phone',
        'title',
        'content',
        'status',
        'reply',
        'handled_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'customer_id' => 'integer',
        'staff_id' => 'integer',
        'handled_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
