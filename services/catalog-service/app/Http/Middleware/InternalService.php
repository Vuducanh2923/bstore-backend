<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InternalService
{

    // Xử lý dữ liệu theo nghiệp vụ của hàm.
    public function handle(Request $request, Closure $next): mixed
    {
        $configuredToken = (string) config('services.internal_service_token');
        $providedToken = (string) $request->header('X-Internal-Service-Token', '');

        if (
            $configuredToken === ''
            || $providedToken === ''
            || ! hash_equals($configuredToken, $providedToken)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Xác thực dịch vụ nội bộ không thành công.',
                'data' => null,
            ], 401);
        }

        return $next($request);
    }
}
