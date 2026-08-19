<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InternalService
{

    // Xử lý dữ liệu theo nghiệp vụ của hàm.
    public function handle(Request $request, Closure $next): mixed
    {
        $expected = config('auth.internal_service_token');
        $provided = $request->header('X-Internal-Service-Token');

        if (
            ! is_string($expected)
            || $expected === ''
            || ! is_string($provided)
            || $provided === ''
            || ! hash_equals($expected, $provided)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Thông tin xác thực nội bộ không hợp lệ',
                'data' => null,
            ], 401);
        }

        return $next($request);
    }
}
