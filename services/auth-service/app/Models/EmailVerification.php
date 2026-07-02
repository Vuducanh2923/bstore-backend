<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerification extends Model
{
    public const TYPE_REGISTER = 'register';

    public const TYPE_FORGOT_PASSWORD = 'forgot_password';

    protected $connection = 'bstore_auth';

    protected $table = 'email_verifications';

    protected $fillable = [
        'email',
        'otp_code',
        'type',
        'expires_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
