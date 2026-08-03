<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class AuthTokenService
{
    private const ALG = 'HS256';

    public function generate(int $userId, string $role = 'CUSTOMER', ?string $email = null): string
    {
        $issuedAt = Carbon::now()->timestamp;
        $expiresAt = $issuedAt + ((int) config('auth.token_ttl', 1440) * 60);

        return $this->encode([
            'token_type' => 'access',
            'sub' => $userId,
            'email' => $email,
            'role' => strtoupper($role),
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $expiresAt,
            'sid' => (string) Str::uuid(),
            'jti' => (string) Str::uuid(),
        ]);
    }

    public function payloadFromRequest(Request $request): ?array
    {
        $token = $request->bearerToken();

        return $token ? $this->decode($token) : null;
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

    private function decode(string $token): ?array
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

        if (($decodedHeader['typ'] ?? null) !== 'JWT' || ($decodedHeader['alg'] ?? null) !== self::ALG) {
            return null;
        }

        $now = Carbon::now()->timestamp;

        if (
            ($decodedPayload['token_type'] ?? null) !== 'access'
            || empty($decodedPayload['sub'])
            || empty($decodedPayload['sid'])
            || empty($decodedPayload['jti'])
            || ! isset($decodedPayload['iat'], $decodedPayload['nbf'], $decodedPayload['exp'])
            || (int) $decodedPayload['nbf'] > $now
            || (int) $decodedPayload['exp'] <= $now
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
        $key = trim((string) config('auth.token_key'));

        if ($key === '') {
            throw new RuntimeException('Bắt buộc phải cấu hình AUTH_TOKEN_KEY.');
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

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
