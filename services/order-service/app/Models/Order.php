<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Order extends Model
{
    public const INVENTORY_RESERVED = 'reserved';

    public const INVENTORY_COMMITTED = 'committed';

    public const INVENTORY_RELEASED = 'released';

    public const INVENTORY_RESTORED = 'restored';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPING = 'shipping';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const WORKFLOW_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_SHIPPING,
        self::STATUS_DELIVERED,
        self::STATUS_COMPLETED,
    ];

    public const CANCEL_REQUEST_NONE = 'none';

    public const CANCEL_REQUEST_PENDING = 'pending';

    public const CANCEL_REQUEST_APPROVED = 'approved';

    public const CANCEL_REQUEST_REJECTED = 'rejected';

    public const REFUND_NONE = 'none';

    public const REFUND_PENDING = 'pending';

    public const REFUND_PROCESSING = 'processing';

    public const REFUND_COMPLETED = 'completed';

    public const REFUND_FAILED = 'failed';

    public const RETURN_NONE = 'none';

    public const RETURN_PENDING = 'pending';

    public const RETURN_APPROVED = 'approved';

    public const RETURN_RECEIVED = 'received';

    public const RETURN_COMPLETED = 'completed';

    public const RETURN_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        'pending' => 'Chờ xác nhận',
        'processing' => 'Đang xử lý',
        'shipping' => 'Đang vận chuyển',
        'delivered' => 'Đã giao hàng',
        'cancelled' => 'Đã hủy',
        'completed' => 'Hoàn tất',
    ];

    public const PAYMENT_STATUS_LABELS = [
        'unpaid' => 'Chưa thanh toán',
        'pending' => 'Đang chờ thanh toán',
        'paid' => 'Đã thanh toán',
        'failed' => 'Thanh toán thất bại',
        'refunded' => 'Đã hoàn tiền',
    ];

    protected $connection = 'bstore_order';

    protected $table = 'orders';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'cart_id',
        'order_code',
        'receiver_name',
        'receiver_phone',
        'receiver_email',
        'shipping_address',
        'shipping_method',
        'payment_method',
        'total_amount',
        'discount_amount',
        'shipping_fee',
        'final_amount',
        'status',
        'cancel_request_status',
        'refund_status',
        'return_status',
        'payment_status',
        'paid_at',
        'delivered_at',
        'assigned_staff_id',
        'assigned_staff_name',
        'assigned_at',
        'processing_note',
        'cancel_reason',
        'return_reason',
        'note',
        'created_at',
        'updated_at',
        'inventory_reference',
        'inventory_state',
        'inventory_updated_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'cart_id' => 'integer',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'delivered_at' => 'datetime',
        'assigned_staff_id' => 'integer',
        'assigned_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'inventory_updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (
                ! $order->getAttribute('created_at')
                && Schema::connection($order->getConnectionName())->hasColumn($order->getTable(), 'created_at')
            ) {
                $order->setAttribute('created_at', now());
            }

            if (
                ! $order->getAttribute('updated_at')
                && Schema::connection($order->getConnectionName())->hasColumn($order->getTable(), 'updated_at')
            ) {
                $order->setAttribute('updated_at', now());
            }
        });

        static::updating(function (Order $order): void {
            if (Schema::connection($order->getConnectionName())->hasColumn($order->getTable(), 'updated_at')) {
                $order->setAttribute('updated_at', now());
            }
        });
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function discounts()
    {
        return $this->hasMany(OrderDiscount::class);
    }

    public function histories()
    {
        return $this->hasMany(OrderHistory::class);
    }

    public function refundRequests()
    {
        return $this->hasMany(RefundRequest::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function warrantyRequests()
    {
        return $this->hasMany(WarrantyRequest::class);
    }

    public function statusLabel(): ?string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function paymentStatusLabel(): ?string
    {
        return self::PAYMENT_STATUS_LABELS[$this->payment_status] ?? $this->payment_status;
    }
}
