<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends Model
{
    use SoftDeletes;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED_AMOUNT = 'fixed_amount';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_EXPIRED = 'expired';

    protected $connection = 'bstore_order';

    protected $table = 'discounts';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'max_discount_amount',
        'min_order_amount',
        'usage_limit',
        'usage_limit_per_customer',
        'used_count',
        'start_date',
        'end_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_limit_per_customer' => 'integer',
        'used_count' => 'integer',
        'created_by' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    // Thực hiện đơn hàng discounts.
    public function orderDiscounts()
    {
        return $this->hasMany(OrderDiscount::class);
    }
}
