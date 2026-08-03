<?php

namespace App\Services;

use App\Models\EmailVerification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthService
{
    public function __construct(
        private readonly AuthTokenService $tokens,
        private readonly EmailVerificationService $emailVerifications,
    ) {}

    public function register(array $data): User
    {
        if (isset($data['name']) && empty($data['full_name'])) {
            $data['full_name'] = $data['name'];
        }

        unset($data['name']);

        $data['email'] = $this->emailVerifications->normalizeEmail($data['email']);

        [$user, $otpCode] = DB::connection('bstore_auth')->transaction(function () use ($data) {
            $data['role_id'] = Role::whereRaw('UPPER(name) = ?', [User::ROLE_CUSTOMER])->firstOrFail()->id;
            $data['password'] = Hash::make($data['password']);
            $data['status'] = 'active';

            if (Schema::connection('bstore_auth')->hasColumn('users', 'email_verified_at')) {
                $data['email_verified_at'] = null;
            }

            $user = User::create($data)->refresh()->load('role');
            [, $otpCode] = $this->emailVerifications->createOtp($user->email, EmailVerification::TYPE_REGISTER);

            return [$user, $otpCode];
        });

        $this->emailVerifications->sendRegisterOtp($user->email, $otpCode);

        return $user;
    }

    public function login(string $email, string $password): array
    {
        $user = User::with('role')->where('email', $this->emailVerifications->normalizeEmail($email))->first();

        if (! $user) {
            return ['status' => 'invalid', 'user' => null];
        }

        if (! $this->usesStrongPasswordHash((string) $user->password)) {
            return ['status' => 'password_reset_required', 'user' => null];
        }

        if (! $this->passwordMatches($password, (string) $user->password)) {
            return ['status' => 'invalid', 'user' => null];
        }

        if (! $user->isActive()) {
            return ['status' => 'inactive', 'user' => null];
        }

        if ($this->requiresEmailVerification($user)) {
            return ['status' => 'email_unverified', 'user' => null];
        }

        if (Hash::needsRehash((string) $user->password)) {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            $user->refresh()->load('role');
        }

        if ($user->getConnection()->getSchemaBuilder()->hasColumn($user->getTable(), 'last_login_at')) {
            $user->forceFill(['last_login_at' => now()])->save();
            $user->refresh()->load('role');
        }

        foreach ($this->tokens->issue($user) as $key => $value) {
            $user->setAttribute($key, $value);
        }

        return ['status' => 'authenticated', 'user' => $user];
    }

    public function refresh(string $refreshToken): array
    {
        $result = $this->tokens->refresh($refreshToken);

        if ($result['status'] !== 'refreshed' || ! $result['user']) {
            return ['status' => $result['status'], 'user' => null];
        }

        $user = $result['user'];

        foreach ($result['credentials'] as $key => $value) {
            $user->setAttribute($key, $value);
        }

        return ['status' => 'refreshed', 'user' => $user];
    }

    public function logout(?string $accessToken, ?string $refreshToken): bool
    {
        $revoked = false;

        if (is_string($accessToken) && $accessToken !== '') {
            $revoked = $this->tokens->revokeAccessToken($accessToken) || $revoked;
        }

        if (is_string($refreshToken) && $refreshToken !== '') {
            $revoked = $this->tokens->revokeRefreshToken($refreshToken) || $revoked;
        }

        return $revoked;
    }

    public function verifyRegisterOtp(string $email, string $otpCode, ?string $ip = null): array
    {
        $result = $this->emailVerifications->verifyOtp($email, $otpCode, EmailVerification::TYPE_REGISTER, $ip);

        if ($result['status'] !== EmailVerificationService::STATUS_VERIFIED) {
            return ['status' => $result['status'], 'user' => null];
        }

        $user = DB::connection('bstore_auth')->transaction(function () use ($email) {
            $user = User::with('role')
                ->where('email', $this->emailVerifications->normalizeEmail($email))
                ->first();

            if (! $user) {
                return null;
            }

            if (Schema::connection('bstore_auth')->hasColumn('users', 'email_verified_at')) {
                $user->forceFill(['email_verified_at' => $user->email_verified_at ?: now()])->save();
            }

            $this->emailVerifications->clearOtps($email, EmailVerification::TYPE_REGISTER);

            return $user->refresh()->load('role');
        });

        return $user
            ? ['status' => EmailVerificationService::STATUS_VERIFIED, 'user' => $user]
            : ['status' => EmailVerificationService::STATUS_INVALID, 'user' => null];
    }

    public function resendRegisterOtp(string $email, ?string $ip = null): string
    {
        if ($this->emailVerifications->tooManySendAttempts($email, EmailVerification::TYPE_REGISTER, $ip)) {
            return EmailVerificationService::STATUS_THROTTLED;
        }

        $this->emailVerifications->hitSendAttempt($email, EmailVerification::TYPE_REGISTER, $ip);

        $user = User::with('role')
            ->where('email', $this->emailVerifications->normalizeEmail($email))
            ->first();

        if (! $user || ! $this->requiresEmailVerification($user)) {
            return EmailVerificationService::STATUS_VERIFIED;
        }

        [, $otpCode] = $this->emailVerifications->createOtp($user->email, EmailVerification::TYPE_REGISTER);
        $this->emailVerifications->sendRegisterOtp($user->email, $otpCode);

        return EmailVerificationService::STATUS_VERIFIED;
    }

    public function requestForgotPasswordOtp(string $email, ?string $ip = null): string
    {
        if ($this->emailVerifications->tooManySendAttempts($email, EmailVerification::TYPE_FORGOT_PASSWORD, $ip)) {
            return EmailVerificationService::STATUS_THROTTLED;
        }

        $this->emailVerifications->hitSendAttempt($email, EmailVerification::TYPE_FORGOT_PASSWORD, $ip);

        $user = User::where('email', $this->emailVerifications->normalizeEmail($email))->first();

        if (! $user) {
            return app()->environment('local')
                ? EmailVerificationService::STATUS_EMAIL_NOT_FOUND
                : EmailVerificationService::STATUS_VERIFIED;
        }

        [, $otpCode] = $this->emailVerifications->createOtp($user->email, EmailVerification::TYPE_FORGOT_PASSWORD);
        $this->emailVerifications->sendForgotPasswordOtp($user->email, $otpCode);

        return EmailVerificationService::STATUS_VERIFIED;
    }

    public function verifyForgotPasswordOtp(string $email, string $otpCode, ?string $ip = null): string
    {
        $result = $this->emailVerifications->verifyOtp($email, $otpCode, EmailVerification::TYPE_FORGOT_PASSWORD, $ip);

        if ($result['status'] !== EmailVerificationService::STATUS_VERIFIED) {
            return $result['status'];
        }

        return EmailVerificationService::STATUS_VERIFIED;
    }

    public function resetPassword(string $email, string $otpCode, string $password, ?string $ip = null): string
    {
        $result = $this->emailVerifications->verifyOtp($email, $otpCode, EmailVerification::TYPE_FORGOT_PASSWORD, $ip);

        if ($result['status'] !== EmailVerificationService::STATUS_VERIFIED) {
            return $result['status'];
        }

        return DB::connection('bstore_auth')->transaction(function () use ($email, $password) {
            $user = User::where('email', $this->emailVerifications->normalizeEmail($email))->first();

            if (! $user) {
                return EmailVerificationService::STATUS_INVALID;
            }

            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            $this->tokens->revokeAllForUser($user);

            $this->emailVerifications->clearForgotPasswordOtps($email);

            return EmailVerificationService::STATUS_VERIFIED;
        });
    }

    private function requiresEmailVerification(User $user): bool
    {
        if (! Schema::connection($user->getConnectionName())->hasColumn($user->getTable(), 'email_verified_at')) {
            return false;
        }

        $user->loadMissing('role');

        return $user->isCustomer() && ! $user->email_verified_at;
    }

    private function passwordMatches(string $password, string $storedPassword): bool
    {
        if ($storedPassword === '') {
            return false;
        }

        return password_verify($password, $storedPassword);
    }

    private function usesStrongPasswordHash(string $storedPassword): bool
    {
        return in_array(password_get_info($storedPassword)['algoName'] ?? 'unknown', ['bcrypt', 'argon2id'], true);
    }
}
