<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AuthTokenService
{
    private const ALG = 'HS256';

    public function generate(int $userId, string $role = 'CUSTOMER', ?string $email = null): string
    {
        $issuedAt = Carbon::now()->timestamp;
        $expiresAt = $issuedAt + ((int) config('auth.token_ttl', 1440) * 60);

        return $this->encode([
            'sub' => $userId,
            'email' => $email,
            'role' => strtoupper($role),
            'iat' => $issuedAt,
            'exp' => $expiresAt,
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

        if (($decodedHeader['alg'] ?? null) !== self::ALG) {
            return null;
        }

        if (($decodedPayload['exp'] ?? 0) < Carbon::now()->timestamp) {
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
        $key = (string) config('auth.token_key', config('app.key'));

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false) {
                return $decoded;
            }
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
