<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly AuthTokenService $tokens) {}

    // Lấy toàn bộ dữ liệu.
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('limit', $request->query('per_page', 15))));
        $users = User::query()
            ->select(['id', 'role_id', 'full_name', 'email', 'phone', 'avatar', 'status', 'created_at'])
            ->with('role:id,name,description')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, (int) $request->query('page', 1)));

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách người dùng thành công',
            'data' => $users->items(),
            'pagination' => [
                'page' => $users->currentPage(),
                'limit' => $users->perPage(),
                'total' => $users->total(),
                'totalPages' => $users->lastPage(),
            ],
        ]);
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function update(Request $request, int|string $id): JsonResponse
    {
        $user = User::find((int) $id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy người dùng',
                'data' => null,
            ], 404);
        }

        $data = $request->validate([
            'full_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'email' => ['sometimes', 'required', 'email', 'max:191', Rule::unique('bstore_auth.users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', Rule::unique('bstore_auth.users', 'phone')->ignore($user->id)],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'district' => ['sometimes', 'nullable', 'string', 'max:100'],
            'ward' => ['sometimes', 'nullable', 'string', 'max:100'],
            'default_shipping_address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'blocked'])],
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

        if (array_key_exists('password', $data) || ! $user->isActive()) {
            $this->tokens->revokeAllForUser($user);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật người dùng thành công',
            'data' => $user->fresh('role'),
        ]);
    }
}
