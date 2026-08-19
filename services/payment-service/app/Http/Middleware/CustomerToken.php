<?php

namespace App\Http\Middleware;

use App\Services\AuthTokenService;
use Closure;
use Illuminate\Http\Request;

class CustomerToken
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly AuthTokenService $tokens) {}

    // Xử lý dữ liệu theo nghiệp vụ của hàm.
    public function handle(Request $request, Closure $next): mixed
    {
        $payload = $this->tokens->payloadFromRequest($request);

        if (! $payload || empty($payload['sub'])) {
            return response()->json(['message' => 'Bạn chưa đăng nhập.', 'code' => 'TOKEN_INVALID'], 401);
        }

        if (strtoupper((string) ($payload['role'] ?? '')) !== 'CUSTOMER') {
            return response()->json(['message' => 'Bạn không có quyền thực hiện chức năng này.', 'code' => 'FORBIDDEN'], 403);
        }

        $request->attributes->set('auth_user', [
            'id' => (int) $payload['sub'],
            'email' => $payload['email'] ?? null,
            'role' => 'CUSTOMER',
        ]);

        return $next($request);
    }
}
