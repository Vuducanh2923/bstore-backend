<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $connection = 'bstore_payment';

    protected $table = 'payment_transactions';

    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'transaction_code',
        'provider',
        'amount',
        'status',
        'response_data',
    ];

    protected $casts = [
        'payment_id' => 'integer',
        'amount' => 'decimal:2',
        'response_data' => 'array',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
