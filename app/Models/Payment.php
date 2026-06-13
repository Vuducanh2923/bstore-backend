<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $connection = 'bstore_payment';

    protected $table = 'payments';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'payment_method',
        'payment_provider',
        'transaction_code',
        'amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
