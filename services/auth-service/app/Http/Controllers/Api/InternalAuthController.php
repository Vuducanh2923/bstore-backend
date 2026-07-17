<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\IntrospectTokenRequest;
use App\Services\AuthTokenService;
use Illuminate\Http\JsonResponse;

class InternalAuthController extends Controller
{
    public function __construct(private readonly AuthTokenService $tokens) {}

    public function introspect(IntrospectTokenRequest $request): JsonResponse
    {
        $result = $this->tokens->validateAccessToken($request->validated('token'));

        if (! $result) {
            return response()->json([
                'success' => true,
                'message' => 'Token khong hoat dong',
                'data' => ['active' => false],
            ]);
        }

        $user = $result['user'];
        $claims = $result['claims'];

        return response()->json([
            'success' => true,
            'message' => 'Token dang hoat dong',
            'data' => [
                'active' => true,
                'token_type' => 'access',
                'sub' => (int) $user->id,
                'email' => (string) $user->email,
                'role' => strtoupper((string) $user->role?->name),
                'status' => (string) $user->status,
                'sid' => (string) $claims['sid'],
                'jti' => (string) $claims['jti'],
                'exp' => (int) $claims['exp'],
            ],
        ]);
    }
}
