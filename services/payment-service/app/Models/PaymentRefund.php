<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRefund extends Model
{
    protected $connection = 'bstore_payment';

    protected $table = 'payment_refunds';

    protected $fillable = [
        'payment_id',
        'order_id',
        'request_id',
        'provider_refund_id',
        'amount',
        'transaction_type',
        'status',
        'response_code',
        'transaction_status',
        'reason',
        'requested_by',
        'request_data',
        'response_data',
        'requested_at',
        'completed_at',
    ];

    protected $casts = [
        'payment_id' => 'integer',
        'order_id' => 'integer',
        'amount' => 'decimal:2',
        'request_data' => 'array',
        'response_data' => 'array',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
