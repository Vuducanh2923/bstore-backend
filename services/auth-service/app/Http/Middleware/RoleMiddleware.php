<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuthTokenService;
use Closure;
use Illuminate\Http\Request;

abstract class RoleMiddleware
{
    public function __construct(private readonly AuthTokenService $tokens) {}

    protected function authorize(Request $request, Closure $next, array $roles): mixed
    {
        $user = $request->user();

        if (! $user instanceof User) {
            $user = $this->tokens->userFromRequest($request);
        }

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'Chua dang nhap',
                'data' => null,
            ], 401);
        }

        $request->setUserResolver(fn () => $user);
        $user->loadMissing('role');

        if (! in_array(strtoupper((string) $user->role?->name), $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Khong co quyen truy cap',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
