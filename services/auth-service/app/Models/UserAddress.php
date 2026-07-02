<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    protected $connection = 'bstore_auth';

    protected $table = 'user_addresses';

    protected $fillable = [
        'user_id',
        'receiver_name',
        'receiver_phone',
        'receiver_email',
        'address',
        'province',
        'district',
        'ward',
        'is_default',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
