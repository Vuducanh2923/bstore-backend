<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REFUNDING = 'refunding';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_REFUNDING,
        self::STATUS_REFUNDED,
    ];

    protected $connection = 'bstore_order';

    protected $table = 'refund_requests';

    protected $fillable = [
        'order_id',
        'customer_id',
        'reason',
        'amount',
        'status',
        'approved_by',
        'approved_at',
        'refund_method',
        'refund_transaction',
        'admin_note',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'customer_id' => 'integer',
        'amount' => 'decimal:2',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
