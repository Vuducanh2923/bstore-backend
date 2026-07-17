<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InternalService
{
    public function handle(Request $request, Closure $next): mixed
    {
        $expected = (string) config('services.internal.token');
        $provided = (string) $request->header('X-Internal-Service-Token', '');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Khong co quyen truy cap noi bo',
                'data' => null,
            ], 401);
        }

        return $next($request);
    }
}
