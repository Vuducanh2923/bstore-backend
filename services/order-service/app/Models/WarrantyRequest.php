<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarrantyRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_PROCESSING,
    ];

    protected $connection = 'bstore_order';

    protected $table = 'warranty_requests';

    protected $fillable = [
        'request_code',
        'user_id',
        'order_id',
        'order_item_id',
        'product_id',
        'type',
        'reason',
        'description',
        'image_url',
        'status',
        'admin_note',
        'rejection_reason',
        'processing_note',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'completed_at',
        'warranty_start_date',
        'warranty_end_date',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'order_id' => 'integer',
        'order_item_id' => 'integer',
        'product_id' => 'integer',
        'approved_by' => 'integer',
        'rejected_by' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
        'warranty_start_date' => 'date',
        'warranty_end_date' => 'date',
    ];

    // Thực hiện đơn hàng.
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Thực hiện đơn hàng mặt hàng.
    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
