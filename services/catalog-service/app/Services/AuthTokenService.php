<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AuthTokenService
{
    private const ALG = 'HS256';

    // Thực hiện generate.
    public function generate(int $userId, string $role = 'CUSTOMER', ?string $email = null): string
    {
        $issuedAt = Carbon::now()->timestamp;

        return $this->encode([
            'sub' => $userId,
            'email' => $email,
            'role' => strtoupper($role),
            'sid' => (string) Str::uuid(),
            'jti' => (string) Str::uuid(),
            'token_type' => 'access',
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $issuedAt + ((int) config('auth.token_ttl', 1440) * 60),
        ]);
    }

    // Thực hiện dữ liệu gửi từ yêu cầu.
    public function payloadFromRequest(Request $request): ?array
    {
        $token = $request->bearerToken();

        return $token ? $this->decode($token) : null;
    }

    // Thực hiện mã hóa.
    private function encode(array $payload): string
    {
        $key = $this->key();

        if ($key === '') {
            throw new \RuntimeException('AUTH_TOKEN_KEY chưa được cấu hình.');
        }

        $segments = [
            $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => self::ALG], JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $segments[] = $this->signature($segments[0], $segments[1], $key);

        return implode('.', $segments);
    }

    // Thực hiện giải mã.
    private function decode(string $token): ?array
    {
        $key = $this->key();
        $segments = explode('.', $token);

        if ($key === '' || count($segments) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $signature] = $segments;
        $header = $this->jsonDecode($encodedHeader);
        $payload = $this->jsonDecode($encodedPayload);

        $now = Carbon::now()->timestamp;

        if (
            ! $header
            || ! $payload
            || ($header['alg'] ?? null) !== self::ALG
            || ($header['typ'] ?? null) !== 'JWT'
            || ! hash_equals($this->signature($encodedHeader, $encodedPayload, $key), $signature)
            || ($payload['token_type'] ?? null) !== 'access'
            || empty($payload['sub'])
            || empty($payload['sid'])
            || empty($payload['jti'])
            || ! isset($payload['nbf'])
            || (int) $payload['nbf'] > $now
            || ! isset($payload['exp'])
            || (int) $payload['exp'] <= $now
        ) {
            return null;
        }

        return $payload;
    }

    // Thực hiện json giải mã.
    private function jsonDecode(string $segment): ?array
    {
        $json = base64_decode($this->base64UrlDecode($segment), true);

        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    // Thực hiện chữ ký.
    private function signature(string $header, string $payload, string $key): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $key, true));
    }

    // Thực hiện khóa.
    private function key(): string
    {
        $key = trim((string) config('auth.token_key'));

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            return $decoded === false || strlen($decoded) < 32 ? '' : $decoded;
        }

        return strlen($key) < 32 ? '' : $key;
    }

    // Thực hiện base64 url mã hóa.
    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    // Thực hiện base64 url giải mã.
    private function base64UrlDecode(string $value): string
    {
        $base64 = strtr($value, '-_', '+/');
        $padding = strlen($base64) % 4;

        return $padding > 0 ? $base64.str_repeat('=', 4 - $padding) : $base64;
    }
}
