<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderServiceClient;
use App\Services\PaymentService;
use App\Services\VnpayRefundService;
use App\Services\VnpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class PaymentController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(
        private readonly PaymentService $payments,
        private readonly VnpayService $vnpay,
        private readonly VnpayRefundService $refunds,
        private readonly OrderServiceClient $orders,
    ) {}

    // Lấy toàn bộ dữ liệu.
    public function index(Request $request): JsonResponse
    {
        $payments = $this->payments->all($request->only(['page', 'limit', 'per_page']));

        return response()->json([
            'success' => true,
            'data' => $payments->items(),
            'pagination' => [
                'page' => $payments->currentPage(),
                'limit' => $payments->perPage(),
                'total' => $payments->total(),
                'totalPages' => $payments->lastPage(),
            ],
        ]);
    }

    // Thực hiện thanh toán bởi đơn hàng.
    public function paymentByOrder(int|string $orderId): JsonResponse
    {
        $payment = $this->payments->paymentForOrder((int) $orderId);

        return $payment
            ? response()->json(['success' => true, 'data' => $payment])
            : response()->json(['success' => false, 'message' => 'Không tìm thấy thanh toán', 'data' => null], 404);
    }

    // Thực hiện hóa đơn bởi đơn hàng.
    public function invoiceByOrder(int|string $orderId): JsonResponse
    {
        $invoice = $this->payments->invoiceForOrder((int) $orderId);

        return $invoice
            ? response()->json(['success' => true, 'data' => $invoice])
            : response()->json(['success' => false, 'message' => 'Không tìm thấy hóa đơn', 'data' => null], 404);
    }

    // Thực hiện synchronize đơn hàng thanh toán trạng thái.
    public function synchronizeOrderPaymentStatus(Request $request, int|string $orderId): JsonResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', Rule::in(['unpaid', 'pending', 'paid', 'failed', 'refunded'])],
            'payment_method' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $payment = $this->payments->synchronizeOrderStatus(
                (int) $orderId,
                $data['payment_method'],
                $data['amount'],
                $data['payment_status'],
            );
        } catch (RuntimeException $exception) {
            return $this->domainError($exception);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đồng bộ trạng thái thanh toán thành công.',
            'data' => [
                'order_id' => $payment->order_id,
                'status' => $data['payment_status'],
                'paid_at' => $payment->paid_at,
            ],
        ]);
    }

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['order_id' => ['required', 'integer', 'min:1']]);

        try {
            $context = $this->orders->paymentContext((int) $data['order_id'], $this->customerId($request));
            $this->assertPaymentContext($context, ['cod']);
            $payment = $this->payments->createAuthoritativeCod((int) $data['order_id'], $this->authoritativeAmount($context));
        } catch (RuntimeException $exception) {
            return $this->domainError($exception);
        }

        return response()->json(['success' => true, 'message' => 'Tạo thanh toán COD thành công', 'data' => $payment], 201);
    }

    // Tạo hoặc lưu vnpay.
    public function createVnpay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'order_info' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $context = $this->orders->paymentContext((int) $data['order_id'], $this->customerId($request));
            $this->assertPaymentContext($context, ['vnpay', 'online']);
            $amount = $this->authoritativeAmount($context);

            if ($amount < 1000 || floor($amount) !== $amount) {
                throw new RuntimeException('Số tiền thanh toán VNPAY phải là số nguyên VND và tối thiểu 1000');
            }

            $result = $this->vnpay->createPaymentUrl(
                (int) $data['order_id'],
                $amount,
                $data['order_info'] ?? "Thanh toán đơn hàng {$data['order_id']}",
                $request->ip() ?: '127.0.0.1',
            );
        } catch (RuntimeException $exception) {
            return $this->domainError($exception);
        }

        return response()->json(['success' => true, 'message' => 'Tạo URL thanh toán VNPAY thành công', 'data' => $result], 201);
    }

    // Thực hiện vnpay trả về.
    public function vnpayReturn(Request $request): JsonResponse
    {
        try {
            $result = $this->vnpay->handleReturn($request->query());
        } catch (Throwable $exception) {
            Log::error('vnpay.return.failed', ['message' => $exception->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Không thể kiểm tra kết quả VNPAY'], 500);
        }

        if (! $result['verified']) {
            return response()->json(['success' => false, 'message' => 'Chữ ký VNPAY không hợp lệ'], 400);
        }

        if (! $result['payment']) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thanh toán hợp lệ'], 404);
        }

        return response()->json([
            'success' => (bool) $result['successful'],
            'message' => $result['successful'] ? 'Thanh toán thành công' : 'Đang chờ IPN xác nhận thanh toán',
            'data' => $result,
        ]);
    }

    // Thực hiện vnpay ipn.
    public function vnpayIpn(Request $request): JsonResponse
    {
        try {
            $result = $this->vnpay->handleIpn($request->query());

            return response()->json($result['response']);
        } catch (Throwable $exception) {
            Log::error('vnpay.ipn.failed', ['message' => $exception->getMessage()]);

            return response()->json(['RspCode' => '99', 'Message' => 'Đã xảy ra lỗi hệ thống.']);
        }
    }

    // Thực hiện hoàn tiền.
    public function refund(Request $request, int|string $orderId): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'requested_by' => ['required', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);
        $key = $data['idempotency_key'] ?? 'refund-'.$orderId.'-'.substr(hash('sha256', $data['amount'].'|'.$data['reason']), 0, 32);

        try {
            $refund = $this->refunds->refund(
                (int) $orderId,
                $data['amount'],
                $data['reason'],
                $data['requested_by'],
                $key,
                $request->ip() ?: '127.0.0.1',
            );
        } catch (RuntimeException $exception) {
            return $this->domainError($exception);
        }

        return response()->json([
            'success' => true,
            'message' => $refund->status === 'refunded' ? 'Hoàn tiền thành công' : 'VNPAY đang xử lý hoàn tiền',
            'data' => $refund,
        ], $refund->status === 'processing' ? 202 : 200);
    }

    // Thực hiện khách hàng id.
    private function customerId(Request $request): int
    {
        return (int) data_get($request->attributes->get('auth_user'), 'id');
    }

    // Thực hiện được xác thực số tiền.
    private function authoritativeAmount(array $context): float
    {
        $amount = $context['final_amount'] ?? $context['amount'] ?? null;

        if (! is_numeric($amount) || (float) $amount < 0) {
            throw new RuntimeException('Dịch vụ đơn hàng không trả về số tiền hợp lệ');
        }

        return (float) $amount;
    }

    // Thực hiện assert thanh toán context.
    private function assertPaymentContext(array $context, array $allowedMethods): void
    {
        $method = strtolower((string) ($context['payment_method'] ?? ''));
        $paymentStatus = strtolower((string) ($context['payment_status'] ?? 'pending'));
        $orderStatus = strtolower((string) ($context['order_status'] ?? $context['status'] ?? 'pending'));

        if (! in_array($method, $allowedMethods, true)) {
            throw new RuntimeException('Phương thức thanh toán của đơn hàng không hợp lệ');
        }

        if ($paymentStatus === 'paid' || in_array($orderStatus, ['cancelled', 'refunded', 'returned'], true)) {
            throw new RuntimeException('Trạng thái đơn hàng không cho phép tạo thanh toán');
        }
    }

    // Thực hiện domain lỗi.
    private function domainError(RuntimeException $exception): JsonResponse
    {
        Log::warning('payment.domain_error', ['message' => $exception->getMessage()]);

        return response()->json(['success' => false, 'message' => $exception->getMessage(), 'data' => null], 422);
    }
}
