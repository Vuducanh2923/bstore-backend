<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly OrderService $orderService) {}

    // Lấy toàn bộ dữ liệu.
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách đơn hàng thành công',
            'data' => $this->orderService->all(),
        ]);
    }

    // Thực hiện khách hàng đơn hàng.
    public function customerOrders(Request $request): JsonResponse
    {
        $orders = $this->orderService->paginatedForCustomer(
            $this->authenticatedUserId($request),
            $request->only(['page', 'limit', 'per_page']),
        );

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách đơn hàng thành công',
            'data' => $this->orderService->serializeOrders($orders->items()),
            'pagination' => [
                'page' => $orders->currentPage(),
                'limit' => $orders->perPage(),
                'hasMore' => $orders->hasMorePages(),
            ],
        ]);
    }

    // Lấy danh sách đơn hàng cho trang quản trị.
    public function adminOrders(Request $request): JsonResponse
    {
        $orders = $this->orderService->adminOrders($request->only([
            'page',
            'limit',
            'per_page',
            'status',
            'payment_status',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách đơn hàng thành công',
            'data' => $this->orderService->serializeAdminOrders($orders->items()),
            'pagination' => [
                'page' => $orders->currentPage(),
                'limit' => $orders->perPage(),
                'total' => $orders->total(),
                'totalPages' => $orders->lastPage(),
            ],
        ]);
    }

    // Thực hiện quản trị đơn hàng chi tiết.
    public function adminOrderDetail(int|string $id): JsonResponse
    {
        $order = $this->orderService->findForAdmin((int) $id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lấy chi tiết đơn hàng thành công',
            'data' => $this->orderService->serializeAdminOrder($order),
        ]);
    }

    // Cập nhật quản trị đơn hàng trạng thái.
    public function updateAdminOrderStatus(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(Order::WORKFLOW_STATUSES)],
            'note' => ['nullable', 'string'],
            'processing_note' => ['nullable', 'string'],
        ]);

        $order = $this->orderService->updateStatus(
            (int) $id,
            $data['status'],
            $this->authenticatedActor($request),
            $data['processing_note'] ?? $data['note'] ?? null,
        );

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật trạng thái đơn hàng thành công',
            'data' => $this->orderService->serializeAdminOrder($order),
        ]);
    }

    // Cập nhật quản trị đơn hàng thanh toán trạng thái.
    public function updateAdminOrderPaymentStatus(Request $request, int|string $id): JsonResponse
    {
        $actor = $this->authenticatedActor($request);

        if (strtoupper((string) ($actor['role'] ?? '')) !== 'ADMIN') {
            throw new AuthorizationException('Không có quyền cập nhật trạng thái thanh toán');
        }

        $data = $request->validate([
            'payment_status' => ['required', Rule::in(array_keys(Order::PAYMENT_STATUS_LABELS))],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = $this->orderService->updateAdminPaymentStatus(
            (int) $id,
            $data['payment_status'],
            $actor,
            $data['note'] ?? null,
        );

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật trạng thái thanh toán thành công.',
            'data' => [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'payment_method' => $order->getAttribute('payment_method'),
                'payment_status' => $order->payment_status,
                'paid_at' => $order->getAttribute('paid_at'),
            ],
        ]);
    }

    // Gán đơn hàng cho nhân viên phụ trách.
    public function assignToStaff(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string'],
            'processing_note' => ['nullable', 'string'],
        ]);

        $order = $this->orderService->assignToStaff(
            (int) $id,
            $this->authenticatedActor($request),
            $data['processing_note'] ?? $data['note'] ?? null,
        );

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Nhận xử lý đơn hàng thành công',
            'data' => $this->orderService->serializeAdminOrder($order),
        ]);
    }

    // Thực hiện khách hàng đơn hàng chi tiết.
    public function customerOrderDetail(Request $request, int|string $id): JsonResponse
    {
        $order = $this->orderService->findForCustomer($this->authenticatedUserId($request), (int) $id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lấy chi tiết đơn hàng thành công',
            'data' => $this->orderService->serializeOrder($order),
        ]);
    }

    // Thực hiện nội bộ khách hàng đơn hàng.
    public function internalCustomerOrders(int|string $userId): JsonResponse
    {
        $orders = $this->orderService->forCustomer((int) $userId);

        return response()->json([
            'success' => true,
            'message' => 'Lấy lịch sử mua hàng thành công',
            'data' => $this->orderService->serializeOrders($orders),
        ]);
    }

    // Thực hiện nội bộ thanh toán context.
    public function internalPaymentContext(Request $request, int|string $orderId): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $context = $this->orderService->paymentContext(
            (int) $orderId,
            isset($data['customer_id']) ? (int) $data['customer_id'] : null,
        );

        if (! $context) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng phù hợp',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lấy ngữ cảnh thanh toán thành công',
            'data' => $context,
        ]);
    }

    // Thực hiện nội bộ update thanh toán trạng thái.
    public function internalUpdatePaymentStatus(Request $request, int|string $orderId): JsonResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', 'string', Rule::in(array_keys(Order::PAYMENT_STATUS_LABELS))],
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
                'message' => 'Không tìm thấy đơn hàng',
                'data' => null,
            ], 404);
        }

        $responseData = [
            'order_id' => $order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->getAttribute('payment_method'),
            'paid_at' => $order->getAttribute('paid_at'),
            'updated' => true,
        ];

        Log::info('order.internal_payment_status.response', [
            'order_id' => (int) $orderId,
            'response' => $responseData,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật trạng thái thanh toán đơn hàng thành công',
            'data' => $responseData,
        ]);
    }

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'payment_method' => strtolower((string) $request->input('payment_method', 'cod')),
            'shipping_method' => strtolower((string) $request->input('shipping_method', 'standard')),
            'items' => $this->consolidateOrderItems($request->input('items')),
        ]);

        $data = $request->validate([
            'receiver_name' => ['required', 'string', 'max:255'],
            'receiver_phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+84|84|0)(?:3|5|7|8|9)\d{8}$/'],
            'receiver_email' => ['nullable', 'email', 'max:191'],
            'shipping_address' => ['required', 'string'],
            'shipping_method' => ['required', 'string', Rule::in(array_keys(config('order.shipping.methods', ['standard' => 'standard'])))],
            'payment_method' => ['required', 'string', Rule::in(config('order.payment_methods', ['cod', 'vnpay']))],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'discounts' => ['sometimes', 'array'],
            'discounts.*.discount_id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'discounts.*.discount_code' => ['nullable', 'string', 'max:191', 'distinct:ignore_case'],
        ]);

        $data['user_id'] = $this->authenticatedUserId($request);

        $order = $this->orderService->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tạo đơn hàng thành công',
            'data' => $order,
        ], 201);
    }

    // Thực hiện consolidate đơn hàng mặt hàng.
    private function consolidateOrderItems(mixed $items): mixed
    {
        if (! is_array($items)) {
            return $items;
        }

        $consolidated = [];
        $positions = [];

        foreach ($items as $item) {
            if (
                ! is_array($item)
                || ! isset($item['product_variant_id'], $item['quantity'])
                || filter_var($item['product_variant_id'], FILTER_VALIDATE_INT) === false
                || filter_var($item['quantity'], FILTER_VALIDATE_INT) === false
            ) {
                $consolidated[] = $item;

                continue;
            }

            $variantId = (int) $item['product_variant_id'];
            $quantity = (int) $item['quantity'];

            if (! array_key_exists($variantId, $positions)) {
                $positions[$variantId] = count($consolidated);
                $item['product_variant_id'] = $variantId;
                $item['quantity'] = $quantity;
                $consolidated[] = $item;

                continue;
            }

            $position = $positions[$variantId];
            $consolidated[$position]['quantity'] += $quantity;
        }

        return array_values($consolidated);
    }

    // Thực hiện yêu cầu hủy.
    public function requestCancel(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $order = $this->orderService->requestCancel(
            $this->authenticatedUserId($request),
            (int) $id,
            $data['reason'],
        );

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Gửi yêu cầu hủy đơn thành công',
            'data' => $this->orderService->serializeOrder($order),
        ]);
    }

    // Cập nhật hủy.
    public function approveCancel(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
        ]);

        $order = $this->orderService->approveCancel(
            (int) $id,
            $this->authenticatedActor($request),
            $data['admin_note'] ?? $data['note'] ?? null,
        );

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Duyệt hủy đơn thành công',
            'data' => $this->orderService->serializeAdminOrder($order),
        ]);
    }

    // Cập nhật hủy.
    public function rejectCancel(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
        ]);

        $order = $this->orderService->rejectCancel(
            (int) $id,
            $this->authenticatedActor($request),
            $data['admin_note'] ?? $data['note'] ?? null,
        );

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Từ chối hủy đơn thành công',
            'data' => $this->orderService->serializeAdminOrder($order),
        ]);
    }

    // Thực hiện yêu cầu trả về.
    public function requestReturn(Request $request, int|string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $order = $this->orderService->requestReturn(
            $this->authenticatedUserId($request),
            (int) $id,
            $data['reason'],
        );

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng', 'data' => null], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Gửi yêu cầu trả hàng thành công',
            'data' => $this->orderService->serializeOrder($order),
        ]);
    }

    // Cập nhật trả về trạng thái.
    public function updateReturnStatus(Request $request, int|string $id, string $status): JsonResponse
    {
        $status = strtolower($status);
        $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! in_array($status, [
            Order::RETURN_APPROVED,
            Order::RETURN_RECEIVED,
            Order::RETURN_COMPLETED,
            Order::RETURN_REJECTED,
        ], true)) {
            abort(404);
        }

        $order = $this->orderService->updateReturnStatus(
            (int) $id,
            $status,
            $this->authenticatedActor($request),
            $request->input('note'),
        );

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng', 'data' => null], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật trạng thái trả hàng thành công',
            'data' => $this->orderService->serializeAdminOrder($order),
        ]);
    }

    // Thực hiện authenticated người dùng id.
    private function authenticatedUserId(Request $request): int
    {
        return (int) data_get($request->attributes->get('auth_user'), 'id');
    }

    // Thực hiện authenticated người thực hiện.
    private function authenticatedActor(Request $request): array
    {
        return (array) $request->attributes->get('auth_user', []);
    }
}
