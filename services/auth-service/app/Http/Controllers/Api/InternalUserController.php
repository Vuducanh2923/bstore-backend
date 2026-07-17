<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class InternalUserController extends Controller
{
    public function show(int|string $id): JsonResponse
    {
        $user = User::query()->with('role:id,name')->find((int) $id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay nguoi dung',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lay thong tin nguoi dung thanh cong',
            'data' => [
                'id' => (int) $user->id,
                'name' => (string) $user->full_name,
                'full_name' => (string) $user->full_name,
                'email' => (string) $user->email,
                'phone' => $user->phone,
                'role' => $user->role ? ['name' => (string) $user->role->name] : null,
                'status' => (string) $user->status,
            ],
        ]);
    }
}
