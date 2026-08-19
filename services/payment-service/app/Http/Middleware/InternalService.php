<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InternalService
{

    // Xử lý dữ liệu theo nghiệp vụ của hàm.
    public function handle(Request $request, Closure $next): mixed
    {
        $expected = (string) config('services.internal.token');
        $provided = (string) $request->header('X-Internal-Service-Token', '');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền truy cập nội bộ',
                'data' => null,
            ], 401);
        }

        return $next($request);
    }
}
