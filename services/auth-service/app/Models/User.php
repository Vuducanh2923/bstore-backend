<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $connection = 'bstore_auth';

    protected $table = 'users';

    public $timestamps = false;

    protected $fillable = [
        'role_id',
        'full_name',
        'email',
        'password',
        'phone',
        'avatar',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
