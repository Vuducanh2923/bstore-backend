<?php

namespace App\Services;

use App\Mail\ForgotPasswordOtpMail;
use App\Mail\RegisterOtpMail;
use App\Models\EmailVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class EmailVerificationService
{
    public const OTP_TTL_MINUTES = 5;

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_THROTTLED = 'throttled';

    public const STATUS_EMAIL_NOT_FOUND = 'email_not_found';

    private const MAX_VERIFY_ATTEMPTS = 5;

    private const MAX_SEND_ATTEMPTS = 3;

    public function createOtp(string $email, string $type): array
    {
        $email = $this->normalizeEmail($email);
        $otpCode = (string) random_int(100000, 999999);

        EmailVerification::query()
            ->where('email', $email)
            ->where('type', $type)
            ->delete();

        $verification = EmailVerification::create([
            'email' => $email,
            'otp_code' => Hash::make($otpCode),
            'type' => $type,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        return [$verification, $otpCode];
    }

    public function sendRegisterOtp(string $email, string $otpCode): void
    {
        Mail::to($this->normalizeEmail($email))
            ->queue(new RegisterOtpMail($otpCode, self::OTP_TTL_MINUTES));
    }

    public function sendForgotPasswordOtp(string $email, string $otpCode): void
    {
        $email = $this->normalizeEmail($email);

        Log::info('Sending forgot password OTP', ['email' => $email]);

        try {
            Mail::to($email)
                ->queue(new ForgotPasswordOtpMail($otpCode, self::OTP_TTL_MINUTES));

            Log::info('Forgot password OTP sent', ['email' => $email]);
        } catch (Throwable $exception) {
            Log::error('Forgot password OTP mail failed', [
                'email' => $email,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    public function verifyOtp(string $email, string $otpCode, string $type, ?string $ip = null, bool $verifiedOnly = false): array
    {
        $email = $this->normalizeEmail($email);
        $key = $this->verifyKey($email, $type, $ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_VERIFY_ATTEMPTS)) {
            return ['status' => self::STATUS_THROTTLED, 'verification' => null];
        }

        $verification = EmailVerification::query()
            ->where('email', $email)
            ->where('type', $type)
            ->when($verifiedOnly, fn ($query) => $query->whereNotNull('verified_at'), fn ($query) => $query->whereNull('verified_at'))
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $verification || ! Hash::check($otpCode, (string) $verification->otp_code)) {
            RateLimiter::hit($key, self::OTP_TTL_MINUTES * 60);

            return ['status' => self::STATUS_INVALID, 'verification' => null];
        }

        RateLimiter::clear($key);

        return ['status' => self::STATUS_VERIFIED, 'verification' => $verification];
    }

    public function tooManySendAttempts(string $email, string $type, ?string $ip = null): bool
    {
        return RateLimiter::tooManyAttempts($this->sendKey($email, $type, $ip), self::MAX_SEND_ATTEMPTS);
    }

    public function hitSendAttempt(string $email, string $type, ?string $ip = null): void
    {
        RateLimiter::hit($this->sendKey($email, $type, $ip), 60);
    }

    public function clearForgotPasswordOtps(string $email): void
    {
        $this->clearOtps($email, EmailVerification::TYPE_FORGOT_PASSWORD);
    }

    public function clearOtps(string $email, string $type): int
    {
        return EmailVerification::query()
            ->where('email', $this->normalizeEmail($email))
            ->where('type', $type)
            ->delete();
    }

    public function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function verifyKey(string $email, string $type, ?string $ip): string
    {
        return 'otp:verify:'.sha1($type.'|'.$this->normalizeEmail($email).'|'.($ip ?: 'unknown'));
    }

    private function sendKey(string $email, string $type, ?string $ip): string
    {
        return 'otp:send:'.sha1($type.'|'.$this->normalizeEmail($email).'|'.($ip ?: 'unknown'));
    }
}
