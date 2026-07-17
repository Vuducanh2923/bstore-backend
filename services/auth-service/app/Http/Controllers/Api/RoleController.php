<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Lay danh sach vai tro thanh cong',
            'data' => Role::query()->orderBy('id')->get(['id', 'name', 'description']),
        ]);
    }
}
