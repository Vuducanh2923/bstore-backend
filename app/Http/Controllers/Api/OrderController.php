<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->orderService->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'order_code' => ['nullable', 'string', 'max:191'],
            'receiver_name' => ['required', 'string', 'max:255'],
            'receiver_phone' => ['required', 'string', 'max:20'],
            'receiver_email' => ['nullable', 'email', 'max:191'],
            'shipping_address' => ['required', 'string'],
            'shipping_method' => ['required', 'string', 'max:50'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'final_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:20'],
            'payment_status' => ['nullable', 'string', 'max:20'],
            'cancel_reason' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'items' => ['sometimes', 'array'],
            'items.*.product_variant_id' => ['required_with:items', 'integer'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.color' => ['nullable', 'string', 'max:50'],
            'items.*.ram' => ['nullable', 'string', 'max:50'],
            'items.*.storage' => ['nullable', 'string', 'max:50'],
            'items.*.price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.subtotal' => ['nullable', 'numeric', 'min:0'],
            'discounts' => ['sometimes', 'array'],
            'discounts.*.discount_id' => ['required_with:discounts', 'integer'],
            'discounts.*.discount_code' => ['required_with:discounts', 'string', 'max:191'],
            'discounts.*.discount_amount' => ['required_with:discounts', 'numeric', 'min:0'],
        ]);

        $order = $this->orderService->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tao don hang thanh cong',
            'data' => $order,
        ], 201);
    }
}
