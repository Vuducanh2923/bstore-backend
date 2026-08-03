<?php

use App\Services\AuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

test('JWT with exp equal to now is expired', function () {
    Carbon::setTestNow('2026-08-03 12:00:00');
    $key = 'payment-jwt-test-key-at-least-32-bytes';
    config(['auth.token_key' => $key]);
    $token = paymentBoundaryExpirationJwt($key, Carbon::now()->timestamp);
    $request = Request::create('/api/payments', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

    expect(app(AuthTokenService::class)->payloadFromRequest($request))->toBeNull();
});

function paymentBoundaryExpirationJwt(string $key, int $exp): string
{
    $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $header = $encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
    $payload = $encode(json_encode([
        'sub' => 1, 'sid' => 'session', 'jti' => 'token', 'token_type' => 'access',
        'nbf' => $exp - 1, 'exp' => $exp,
    ], JSON_THROW_ON_ERROR));
    $signature = $encode(hash_hmac('sha256', "{$header}.{$payload}", $key, true));

    return "{$header}.{$payload}.{$signature}";
}
