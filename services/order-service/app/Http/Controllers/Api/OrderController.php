<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Lay danh sach don hang thanh cong',
            'data' => $this->orderService->all(),
        ]);
    }

    public function customerOrders(Request $request): JsonResponse
    {
        $orders = $this->orderService->forCustomer($this->authenticatedUserId($request));

        return response()->json([
            'success' => true,
            'message' => 'Lay danh sach don hang thanh cong',
            'data' => $this->orderService->serializeOrders($orders),
        ]);
    }

    public function adminOrders(): JsonResponse
    {
        $orders = $this->orderService->adminOrders();

        return response()->json([
            'success' => true,
            'message' => 'Lay danh sach don hang thanh cong',
            'data' => $this->orderService->serializeAdminOrders($orders),
        ]);
    }

    public function adminOrderDetail(int|string $id): JsonResponse
    {
        $order = $this->orderService->findForAdmin((int) $id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay don hang',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lay chi tiet don hang thanh cong',
            'data' => $this->orderService->serializeAdminOrder($order),
        ]);
    }

    public function updateAdminOrderStatus(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(Order::STATUS_LABELS))],
        ]);

        $order = $this->orderService->updateStatus((int) $id, $data['status']);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay don hang',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat trang thai don hang thanh cong',
            'data' => $this->orderService->serializeAdminOrder($order),
        ]);
    }

    public function customerOrderDetail(Request $request, int|string $id): JsonResponse
    {
        $order = $this->orderService->findForCustomer($this->authenticatedUserId($request), (int) $id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay don hang',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lay chi tiet don hang thanh cong',
            'data' => $this->orderService->serializeOrder($order),
        ]);
    }

    public function internalCustomerOrders(int|string $userId): JsonResponse
    {
        $orders = $this->orderService->forCustomer((int) $userId);

        return response()->json([
            'success' => true,
            'message' => 'Lay lich su mua hang thanh cong',
            'data' => $this->orderService->serializeOrders($orders),
        ]);
    }

    public function internalUpdatePaymentStatus(Request $request, int|string $orderId): JsonResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', 'string', Rule::in(array_keys(Order::PAYMENT_STATUS_LABELS))],
            'status' => ['nullable', 'string', Rule::in(array_keys(Order::STATUS_LABELS))],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
        ]);

        Log::info('order.internal_payment_status.request', [
            'order_id' => (int) $orderId,
            'payload' => $data,
        ]);

        $order = $this->orderService->updatePaymentStatus((int) $orderId, $data);

        if (! $order) {
            Log::warning('order.internal_payment_status.not_found', [
                'order_id' => (int) $orderId,
                'payload' => $data,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay don hang',
                'data' => null,
            ], 404);
        }

        $responseData = [
            'order_id' => $order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->getAttribute('payment_method'),
            'paid_at' => $order->getAttribute('paid_at'),
        ];

        Log::info('order.internal_payment_status.response', [
            'order_id' => (int) $orderId,
            'response' => $responseData,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat trang thai thanh toan don hang thanh cong',
            'data' => $responseData,
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
            'payment_method' => ['nullable', 'string', 'max:50'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'final_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:20'],
            'payment_status' => ['nullable', 'string', 'max:20'],
            'cancel_reason' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'items' => ['sometimes', 'array'],
            'items.*.product_variant_id' => ['required_with:items', 'integer'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.product_image' => ['nullable', 'string', 'max:500'],
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

    private function authenticatedUserId(Request $request): int
    {
        return (int) data_get($request->attributes->get('auth_user'), 'id');
    }
}
