<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InternalService
{
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
                'message' => 'Thong tin xac thuc noi bo khong hop le',
                'data' => null,
            ], 401);
        }

        return $next($request);
    }
}
