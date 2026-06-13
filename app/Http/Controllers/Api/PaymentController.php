<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->paymentService->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_provider' => ['nullable', 'string', 'max:50'],
            'transaction_code' => ['nullable', 'string', 'max:191'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:20'],
            'paid_at' => ['nullable', 'date'],
            'transactions' => ['sometimes', 'array'],
            'transactions.*.transaction_code' => ['required_with:transactions', 'string', 'max:191'],
            'transactions.*.provider' => ['required_with:transactions', 'string', 'max:100'],
            'transactions.*.amount' => ['required_with:transactions', 'numeric', 'min:0'],
            'transactions.*.status' => ['required_with:transactions', 'string', 'max:20'],
            'transactions.*.response_data' => ['nullable'],
        ]);

        $payment = $this->paymentService->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tao thanh toan thanh cong',
            'data' => $payment,
        ], 201);
    }
}
