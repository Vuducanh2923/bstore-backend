<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    // Khai báo kiểu chuyển đổi cho các thuộc tính của model.
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

    // Thực hiện booted.
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

    // Thực hiện vai trò.
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // Thực hiện addresses.
    public function addresses()
    {
        return $this->hasMany(UserAddress::class, 'user_id');
    }

    // Thực hiện xác thực sessions.
    public function authSessions(): HasMany
    {
        return $this->hasMany(AuthSession::class, 'user_id');
    }

    // Kiểm tra đang hoạt động.
    public function isActive(): bool
    {
        return strtolower(trim((string) $this->status)) === 'active';
    }

    // Kiểm tra quản trị.
    public function isAdmin(): bool
    {
        $this->loadMissing('role');

        return strtoupper((string) $this->role?->name) === self::ROLE_ADMIN;
    }

    // Kiểm tra nhân viên.
    public function isStaff(): bool
    {
        $this->loadMissing('role');

        return strtoupper((string) $this->role?->name) === self::ROLE_STAFF;
    }

    // Kiểm tra khách hàng.
    public function isCustomer(): bool
    {
        $this->loadMissing('role');

        return strtoupper((string) $this->role?->name) === self::ROLE_CUSTOMER;
    }

    // Thực hiện assignable roles.
    public static function assignableRoles(): array
    {
        return [
            self::ROLE_STAFF,
            self::ROLE_CUSTOMER,
        ];
    }
}
