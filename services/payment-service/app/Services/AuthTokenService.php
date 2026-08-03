<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

class AuthTokenService
{
    private const ALG = 'HS256';

    public function payloadFromRequest(Request $request): ?array
    {
        $token = $request->bearerToken();

        return $token ? $this->decode($token) : null;
    }

    private function decode(string $token): ?array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $segments;
        $key = $this->key();

        if ($key === '' || ! hash_equals($this->signature($header, $payload, $key), $signature)) {
            return null;
        }

        $decodedHeader = $this->jsonDecode($header);
        $decodedPayload = $this->jsonDecode($payload);

        if (($decodedHeader['alg'] ?? null) !== self::ALG
            || ($decodedHeader['typ'] ?? null) !== 'JWT'
            || empty($decodedPayload['sid'])
            || empty($decodedPayload['jti'])
            || empty($decodedPayload['sub'])
            || ($decodedPayload['token_type'] ?? null) !== 'access'
            || (isset($decodedPayload['nbf']) && (int) $decodedPayload['nbf'] > Carbon::now()->timestamp)
            || ! isset($decodedPayload['exp'])
            || (int) $decodedPayload['exp'] <= Carbon::now()->timestamp) {
            return null;
        }

        return $decodedPayload;
    }

    private function jsonDecode(string $segment): ?array
    {
        $json = base64_decode($this->base64UrlDecode($segment), true);
        $decoded = $json === false ? null : json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function signature(string $header, string $payload, string $key): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $key, true));
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

        return $padding > 0 ? $base64.str_repeat('=', 4 - $padding) : $base64;
    }
}
