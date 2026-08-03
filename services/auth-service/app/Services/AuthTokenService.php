<?php

namespace App\Services;

use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AuthTokenService
{
    private const ALG = 'HS256';

    public function issue(User $user): array
    {
        $this->key();
        $user->loadMissing('role');

        if (! $user->isActive()) {
            throw new RuntimeException('Không thể cấp token cho tài khoản đã bị vô hiệu hóa.');
        }

        $now = Carbon::now();
        $refreshToken = $this->randomToken();
        $session = AuthSession::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'access_jti' => (string) Str::uuid(),
            'refresh_token_hash' => $this->refreshHash($refreshToken),
            'refresh_expires_at' => $now->copy()->addMinutes($this->refreshTtlMinutes()),
            'last_used_at' => $now,
        ]);

        return $this->credentials($user, $session, $refreshToken, $now);
    }

    /**
     * Backward-compatible helper for callers that only need an access token.
     */
    public function generate(User $user): string
    {
        return $this->issue($user)['token'];
    }

    public function refresh(string $refreshToken): array
    {
        if ($refreshToken === '') {
            return ['status' => 'invalid', 'user' => null, 'credentials' => null];
        }

        return DB::connection('bstore_auth')->transaction(function () use ($refreshToken) {
            $now = Carbon::now();
            $session = AuthSession::query()
                ->where('refresh_token_hash', $this->refreshHash($refreshToken))
                ->lockForUpdate()
                ->first();

            if (! $session || $session->revoked_at) {
                return ['status' => 'invalid', 'user' => null, 'credentials' => null];
            }

            if (! $session->refresh_expires_at || $session->refresh_expires_at->lte($now)) {
                $session->forceFill(['revoked_at' => $now])->save();

                return ['status' => 'invalid', 'user' => null, 'credentials' => null];
            }

            $user = $session->user()->with('role')->first();

            if (! $user || ! $user->isActive()) {
                $session->forceFill(['revoked_at' => $now])->save();

                return ['status' => 'inactive', 'user' => $user, 'credentials' => null];
            }

            $rotatedRefreshToken = $this->randomToken();
            $session->forceFill([
                'access_jti' => (string) Str::uuid(),
                'refresh_token_hash' => $this->refreshHash($rotatedRefreshToken),
                'last_used_at' => $now,
            ])->save();

            return [
                'status' => 'refreshed',
                'user' => $user,
                'credentials' => $this->credentials($user, $session, $rotatedRefreshToken, $now),
            ];
        });
    }

    public function userFromRequest(Request $request): ?User
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        return $this->validateAccessToken($token)['user'] ?? null;
    }

    public function validateAccessToken(string $token): ?array
    {
        $claims = $this->decode($token);

        if (
            ! $claims
            || ($claims['token_type'] ?? null) !== 'access'
            || empty($claims['sid'])
            || empty($claims['jti'])
            || empty($claims['sub'])
        ) {
            return null;
        }

        $session = AuthSession::query()
            ->with('user.role')
            ->find((string) $claims['sid']);

        if (
            ! $session
            || $session->revoked_at
            || ! $session->refresh_expires_at
            || $session->refresh_expires_at->lte(Carbon::now())
            || ! hash_equals((string) $session->access_jti, (string) $claims['jti'])
            || (int) $session->user_id !== (int) $claims['sub']
        ) {
            return null;
        }

        $user = $session->user;

        if (! $user || ! $user->isActive()) {
            return null;
        }

        return [
            'user' => $user,
            'session' => $session,
            'claims' => $claims,
        ];
    }

    public function revokeAccessToken(string $token): bool
    {
        $claims = $this->decode($token, false);

        if (
            ! $claims
            || ($claims['token_type'] ?? null) !== 'access'
            || empty($claims['sid'])
            || empty($claims['jti'])
            || empty($claims['sub'])
        ) {
            return false;
        }

        return AuthSession::query()
            ->whereKey((string) $claims['sid'])
            ->where('access_jti', (string) $claims['jti'])
            ->where('user_id', (int) $claims['sub'])
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now()]) === 1;
    }

    public function revokeRefreshToken(string $refreshToken): bool
    {
        if ($refreshToken === '') {
            return false;
        }

        return AuthSession::query()
            ->where('refresh_token_hash', $this->refreshHash($refreshToken))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now()]) === 1;
    }

    public function revokeAllForUser(User|int $user): int
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        return AuthSession::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now()]);
    }

    private function credentials(User $user, AuthSession $session, string $refreshToken, Carbon $now): array
    {
        $expiresIn = $this->accessTtlSeconds();

        return [
            'token' => $this->encode([
                'token_type' => 'access',
                'sub' => (int) $user->id,
                'email' => (string) $user->email,
                'role' => strtoupper((string) $user->role?->name),
                'sid' => (string) $session->id,
                'jti' => (string) $session->access_jti,
                'iat' => $now->timestamp,
                'nbf' => $now->timestamp,
                'exp' => $now->timestamp + $expiresIn,
            ]),
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
        ];
    }

    private function encode(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => self::ALG,
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];

        $segments[] = $this->signature($segments[0], $segments[1]);

        return implode('.', $segments);
    }

    private function decode(string $token, bool $validateLifetime = true): ?array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $segments;

        if (! hash_equals($this->signature($header, $payload), $signature)) {
            return null;
        }

        $decodedHeader = $this->jsonDecode($header);
        $decodedPayload = $this->jsonDecode($payload);

        if (
            ($decodedHeader['alg'] ?? null) !== self::ALG
            || ($decodedHeader['typ'] ?? null) !== 'JWT'
            || ! is_numeric($decodedPayload['exp'] ?? null)
        ) {
            return null;
        }

        $now = Carbon::now()->timestamp;

        if (
            $validateLifetime
            && ((int) $decodedPayload['exp'] <= $now || (int) ($decodedPayload['nbf'] ?? 0) > $now)
        ) {
            return null;
        }

        return $decodedPayload;
    }

    private function jsonDecode(string $segment): ?array
    {
        $json = base64_decode($this->base64UrlDecode($segment), true);

        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function signature(string $header, string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $this->key(), true));
    }

    private function key(): string
    {
        $configured = config('auth.token_key');

        if (! is_string($configured) || trim($configured) === '') {
            throw new RuntimeException('Bắt buộc phải cấu hình AUTH_TOKEN_KEY.');
        }

        $key = $configured;

        if (str_starts_with($configured, 'base64:')) {
            $decoded = base64_decode(substr($configured, 7), true);

            if ($decoded === false) {
                throw new RuntimeException('AUTH_TOKEN_KEY không phải chuỗi base64 hợp lệ.');
            }

            $key = $decoded;
        }

        if (strlen($key) < 32) {
            throw new RuntimeException('AUTH_TOKEN_KEY phải chứa ít nhất 32 byte.');
        }

        return $key;
    }

    private function randomToken(): string
    {
        return $this->base64UrlEncode(random_bytes(64));
    }

    private function refreshHash(string $refreshToken): string
    {
        return hash('sha256', $refreshToken);
    }

    private function accessTtlSeconds(): int
    {
        return max(1, (int) config('auth.access_token_ttl', 15)) * 60;
    }

    private function refreshTtlMinutes(): int
    {
        return max(1, (int) config('auth.refresh_token_ttl', 43200));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $base64 = strtr($value, '-_', '+/');
        $padding = strlen($base64) % 4;

        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        return $base64;
    }
}
