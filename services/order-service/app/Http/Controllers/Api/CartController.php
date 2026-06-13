<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'status' => ['nullable', 'string', 'max:20'],
            'items' => ['sometimes', 'array'],
            'items.*.product_variant_id' => ['required_with:items', 'integer'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.color' => ['nullable', 'string', 'max:50'],
            'items.*.ram' => ['nullable', 'string', 'max:50'],
            'items.*.storage' => ['nullable', 'string', 'max:50'],
            'items.*.price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.subtotal' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cart = $this->cartService->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tao gio hang thanh cong',
            'data' => $cart,
        ], 201);
    }
}
