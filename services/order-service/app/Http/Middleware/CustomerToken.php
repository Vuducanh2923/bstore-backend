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
                'message' => 'Bạn chưa đăng nhập.',
                'code' => 'TOKEN_INVALID',
            ], 401);
        }

        // Administrators may also use the storefront for their own purchases.
        // Administrative operations remain protected by the separate
        // `admin.token` middleware and `/admin` route prefix.
        if (! in_array(strtoupper((string) ($payload['role'] ?? '')), ['CUSTOMER', 'ADMIN'], true)) {
            return response()->json([
                'message' => 'Bạn không có quyền thực hiện chức năng này.',
                'code' => 'FORBIDDEN',
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
