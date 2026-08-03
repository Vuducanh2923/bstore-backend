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
use RuntimeException;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly VnpayService $vnpay,
        private readonly VnpayRefundService $refunds,
        private readonly OrderServiceClient $orders,
    ) {}

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

    public function paymentByOrder(int|string $orderId): JsonResponse
    {
        $payment = $this->payments->paymentForOrder((int) $orderId);

        return $payment
            ? response()->json(['success' => true, 'data' => $payment])
            : response()->json(['success' => false, 'message' => 'Khong tim thay thanh toan', 'data' => null], 404);
    }

    public function invoiceByOrder(int|string $orderId): JsonResponse
    {
        $invoice = $this->payments->invoiceForOrder((int) $orderId);

        return $invoice
            ? response()->json(['success' => true, 'data' => $invoice])
            : response()->json(['success' => false, 'message' => 'Khong tim thay hoa don', 'data' => null], 404);
    }

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

        return response()->json(['success' => true, 'message' => 'Tao thanh toan COD thanh cong', 'data' => $payment], 201);
    }

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
                throw new RuntimeException('So tien thanh toan VNPAY phai la so nguyen VND va toi thieu 1000');
            }

            $result = $this->vnpay->createPaymentUrl(
                (int) $data['order_id'],
                $amount,
                $data['order_info'] ?? "Thanh toan don hang {$data['order_id']}",
                $request->ip() ?: '127.0.0.1',
            );
        } catch (RuntimeException $exception) {
            return $this->domainError($exception);
        }

        return response()->json(['success' => true, 'message' => 'Tao URL thanh toan VNPAY thanh cong', 'data' => $result], 201);
    }

    public function vnpayReturn(Request $request): JsonResponse
    {
        try {
            $result = $this->vnpay->handleReturn($request->query());
        } catch (Throwable $exception) {
            Log::error('vnpay.return.failed', ['message' => $exception->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Khong the kiem tra ket qua VNPAY'], 500);
        }

        if (! $result['verified']) {
            return response()->json(['success' => false, 'message' => 'Chu ky VNPAY khong hop le'], 400);
        }

        if (! $result['payment']) {
            return response()->json(['success' => false, 'message' => 'Khong tim thay thanh toan hop le'], 404);
        }

        return response()->json([
            'success' => (bool) $result['successful'],
            'message' => $result['successful'] ? 'Thanh toan thanh cong' : 'Dang cho IPN xac nhan thanh toan',
            'data' => $result,
        ]);
    }

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
            'message' => $refund->status === 'refunded' ? 'Hoan tien thanh cong' : 'VNPAY dang xu ly hoan tien',
            'data' => $refund,
        ], $refund->status === 'processing' ? 202 : 200);
    }

    private function customerId(Request $request): int
    {
        return (int) data_get($request->attributes->get('auth_user'), 'id');
    }

    private function authoritativeAmount(array $context): float
    {
        $amount = $context['final_amount'] ?? $context['amount'] ?? null;

        if (! is_numeric($amount) || (float) $amount < 0) {
            throw new RuntimeException('Order Service khong tra ve so tien hop le');
        }

        return (float) $amount;
    }

    private function assertPaymentContext(array $context, array $allowedMethods): void
    {
        $method = strtolower((string) ($context['payment_method'] ?? ''));
        $paymentStatus = strtolower((string) ($context['payment_status'] ?? 'pending'));
        $orderStatus = strtolower((string) ($context['order_status'] ?? $context['status'] ?? 'pending'));

        if (! in_array($method, $allowedMethods, true)) {
            throw new RuntimeException('Phuong thuc thanh toan cua don hang khong hop le');
        }

        if ($paymentStatus === 'paid' || in_array($orderStatus, ['cancelled', 'refunded', 'returned'], true)) {
            throw new RuntimeException('Trang thai don hang khong cho phep tao thanh toan');
        }
    }

    private function domainError(RuntimeException $exception): JsonResponse
    {
        Log::warning('payment.domain_error', ['message' => $exception->getMessage()]);

        return response()->json(['success' => false, 'message' => $exception->getMessage(), 'data' => null], 422);
    }
}
