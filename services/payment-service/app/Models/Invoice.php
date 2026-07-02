<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $connection = 'bstore_payment';

    protected $table = 'invoices';

    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'order_id',
        'invoice_code',
        'total_amount',
        'issued_at',
    ];

    protected $casts = [
        'payment_id' => 'integer',
        'order_id' => 'integer',
        'total_amount' => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
