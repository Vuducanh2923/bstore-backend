<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => User::with('role')->orderByDesc('id')->get(),
        ]);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $user = User::find((int) $id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay nguoi dung',
            ], 404);
        }

        $data = $request->validate([
            'role_id' => ['sometimes', 'required', 'integer', Rule::exists('bstore_auth.roles', 'id')],
            'full_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'email' => ['sometimes', 'required', 'email', 'max:191', Rule::unique('bstore_auth.users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        if (isset($data['name']) && empty($data['full_name'])) {
            $data['full_name'] = $data['name'];
        }

        unset($data['name']);

        if (array_key_exists('password', $data)) {
            if ($data['password']) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
        }

        $user->fill($data);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat nguoi dung thanh cong',
            'data' => $user->fresh('role'),
        ]);
    }
}
