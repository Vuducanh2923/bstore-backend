<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthSession extends Model
{
    protected $connection = 'bstore_auth';

    protected $table = 'auth_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'access_jti',
        'refresh_token_hash',
        'refresh_expires_at',
        'last_used_at',
        'revoked_at',
    ];

    protected $hidden = [
        'refresh_token_hash',
    ];

    // Khai báo kiểu chuyển đổi cho các thuộc tính của model.
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'refresh_expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    // Cung cấp trạng thái và thao tác cho dữ liệu theo nghiệp vụ của hàm.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
