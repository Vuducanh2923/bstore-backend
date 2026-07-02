<?php

namespace App\Http\Middleware;

use App\Services\AuthTokenService;
use Closure;
use Illuminate\Http\Request;

class CustomerToken
{
    public function __construct(private readonly AuthTokenService $tokens) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $payload = $this->tokens->payloadFromRequest($request);

        if (! $payload || empty($payload['sub'])) {
            return response()->json([
                'success' => false,
                'message' => 'Chua dang nhap',
                'data' => null,
            ], 401);
        }

        if (strtoupper((string) ($payload['role'] ?? '')) !== 'CUSTOMER') {
            return response()->json([
                'success' => false,
                'message' => 'Khong co quyen truy cap',
                'data' => null,
            ], 403);
        }

        $request->attributes->set('auth_user', [
            'id' => (int) $payload['sub'],
            'email' => $payload['email'] ?? null,
            'role' => strtoupper((string) ($payload['role'] ?? '')),
        ]);

        return $next($request);
    }
}
