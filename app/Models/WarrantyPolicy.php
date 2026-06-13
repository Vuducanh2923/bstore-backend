<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarrantyPolicy extends Model
{
    protected $connection = 'bstore_catalog';

    protected $table = 'warranty_policies';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'duration_months',
        'return_days',
        'exchange_days',
        'repair_support',
        'description',
        'status',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'return_days' => 'integer',
        'exchange_days' => 'integer',
        'repair_support' => 'boolean',
    ];
}
