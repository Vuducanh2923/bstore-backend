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
                'message' => 'Bạn chưa đăng nhập.',
                'code' => 'TOKEN_INVALID',
            ], 401);
        }

        if (! $user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Tai khoan da bi vo hieu hoa',
                'data' => null,
            ], 403);
        }

        $request->setUserResolver(fn () => $user);
        $user->loadMissing('role');

        if (! in_array(strtoupper((string) $user->role?->name), $roles, true)) {
            return response()->json([
                'message' => 'Bạn không có quyền thực hiện chức năng này.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        return $next($request);
    }
}
