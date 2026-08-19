<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OrderServiceClient
{

    // Thực hiện thanh toán context.
    public function paymentContext(int $orderId, int $customerId): array
    {
        try {
            $response = $this->request()->get($this->url("/api/internal/orders/{$orderId}/payment-context"), [
                'customer_id' => $customerId,
            ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Không kết nối được Dịch vụ đơn hàng', previous: $exception);
        }

        if (! $response->successful() || ! is_array($response->json('data'))) {
            throw new RuntimeException((string) ($response->json('message') ?: 'Dịch vụ đơn hàng từ chối ngữ cảnh thanh toán'));
        }

        return $response->json('data');
    }

    // Thực hiện mark thanh toán paid.
    public function markPaymentPaid(int $orderId): array
    {
        return $this->updatePaymentStatus($orderId, [
            'payment_status' => 'paid',
            'payment_method' => 'vnpay',
            'paid_at' => now()->toDateTimeString(),
        ]);
    }

    // Thực hiện mark thanh toán thất bại.
    public function markPaymentFailed(int $orderId, string $reason): array
    {
        return $this->updatePaymentStatus($orderId, [
            'payment_status' => 'failed',
            'reason' => $reason,
        ]);
    }

    // Làm mới hoặc đặt lại giỏ hàng cho paid đơn hàng.
    public function clearCartForPaidOrder(int $orderId): array
    {
        try {
            $response = $this->request()->post($this->url("/api/internal/orders/{$orderId}/cart/clear"), [
                'source' => 'payment-service',
                'reason' => 'vnpay_paid',
            ]);
        } catch (ConnectionException $exception) {
            Log::error('payment.order_cart_clear.connection_failed', ['order_id' => $orderId, 'message' => $exception->getMessage()]);

            return ['cleared' => false, 'status' => null, 'message' => 'Không kết nối được Dịch vụ đơn hàng'];
        }

        return [
            'cleared' => $response->successful(),
            'status' => $response->status(),
            'response' => $response->json(),
        ];
    }

    // Cập nhật thanh toán trạng thái.
    private function updatePaymentStatus(int $orderId, array $payload): array
    {
        try {
            $response = $this->request()->patch(
                $this->url("/api/internal/orders/{$orderId}/payment-status"),
                $payload,
            );
        } catch (ConnectionException $exception) {
            Log::error('payment.order_status.connection_failed', [
                'order_id' => $orderId,
                'payload' => $payload,
                'message' => $exception->getMessage(),
            ]);

            return ['updated' => false, 'status' => null, 'message' => 'Không kết nối được Dịch vụ đơn hàng'];
        }

        return [
            'updated' => $response->successful(),
            'status' => $response->status(),
            'response' => $response->json(),
        ];
    }

    // Thực hiện yêu cầu.
    private function request(): PendingRequest
    {
        $token = trim((string) config('services.internal.token'));

        if ($token === '') {
            throw new RuntimeException('INTERNAL_SERVICE_TOKEN chưa được cấu hình');
        }

        return Http::acceptJson()
            ->withHeaders(['X-Internal-Service-Token' => $token])
            ->connectTimeout((int) config('services.connect_timeout', 2))
            ->timeout((int) config('services.timeout', 5))
            ->retry(2, 100, null, false);
    }

    // Thực hiện url.
    private function url(string $path): string
    {
        $baseUrl = rtrim((string) config('services.order.url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('ORDER_SERVICE_URL chưa được cấu hình');
        }

        return $baseUrl.$path;
    }
}
