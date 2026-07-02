<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'ADMIN';

    public const ROLE_STAFF = 'STAFF';

    public const ROLE_CUSTOMER = 'CUSTOMER';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_STAFF,
        self::ROLE_CUSTOMER,
    ];

    protected $connection = 'bstore_auth';

    protected $table = 'users';

    public $timestamps = false;

    protected $fillable = [
        'role_id',
        'full_name',
        'email',
        'email_verified_at',
        'password',
        'phone',
        'address',
        'province',
        'district',
        'ward',
        'default_shipping_address',
        'gender',
        'date_of_birth',
        'avatar',
        'status',
        'last_login_at',
        'created_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date:Y-m-d',
            'last_login_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (
                ! $user->getAttribute('created_at')
                && Schema::connection($user->getConnectionName())->hasColumn($user->getTable(), 'created_at')
            ) {
                $user->setAttribute('created_at', now());
            }
        });
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function addresses()
    {
        return $this->hasMany(UserAddress::class, 'user_id');
    }

    public function isAdmin(): bool
    {
        $this->loadMissing('role');

        return strtoupper((string) $this->role?->name) === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        $this->loadMissing('role');

        return strtoupper((string) $this->role?->name) === self::ROLE_STAFF;
    }

    public function isCustomer(): bool
    {
        $this->loadMissing('role');

        return strtoupper((string) $this->role?->name) === self::ROLE_CUSTOMER;
    }

    public static function assignableRoles(): array
    {
        return [
            self::ROLE_STAFF,
            self::ROLE_CUSTOMER,
        ];
    }
}
