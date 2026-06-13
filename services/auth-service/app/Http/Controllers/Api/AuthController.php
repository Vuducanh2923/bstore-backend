<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_id' => ['nullable', 'integer'],
            'full_name' => ['required_without:name', 'string', 'max:100'],
            'name' => ['required_without:full_name', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('bstore_auth.users', 'email')],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'avatar' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $this->authService->register($data);

        return response()->json([
            'success' => true,
            'message' => 'Dang ky thanh cong',
            'data' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->authService->login($data['email'], $data['password']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email hoac mat khau khong dung',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dang nhap thanh cong',
            'data' => $user,
        ]);
    }
}
