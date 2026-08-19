<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{

    // Lấy toàn bộ dữ liệu.
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách vai trò thành công',
            'data' => Role::query()->orderBy('id')->get(['id', 'name', 'description']),
        ]);
    }
}
