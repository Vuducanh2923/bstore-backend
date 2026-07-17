<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OrderServiceClient
{
    public function paymentContext(int $orderId, int $customerId): array
    {
        try {
            $response = $this->request()->get($this->url("/api/internal/orders/{$orderId}/payment-context"), [
                'customer_id' => $customerId,
            ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Khong ket noi duoc Order Service', previous: $exception);
        }

        if (! $response->successful() || ! is_array($response->json('data'))) {
            throw new RuntimeException((string) ($response->json('message') ?: 'Order Service tu choi ngu canh thanh toan'));
        }

        return $response->json('data');
    }

    public function markPaymentPaid(int $orderId): array
    {
        return $this->updatePaymentStatus($orderId, [
            'payment_status' => 'paid',
            'payment_method' => 'vnpay',
            'paid_at' => now()->toDateTimeString(),
        ]);
    }

    public function markPaymentFailed(int $orderId, string $reason): array
    {
        return $this->updatePaymentStatus($orderId, [
            'payment_status' => 'failed',
            'reason' => $reason,
        ]);
    }

    public function clearCartForPaidOrder(int $orderId): array
    {
        try {
            $response = $this->request()->post($this->url("/api/internal/orders/{$orderId}/cart/clear"), [
                'source' => 'payment-service',
                'reason' => 'vnpay_paid',
            ]);
        } catch (ConnectionException $exception) {
            Log::error('payment.order_cart_clear.connection_failed', ['order_id' => $orderId, 'message' => $exception->getMessage()]);

            return ['cleared' => false, 'status' => null, 'message' => 'Khong ket noi duoc Order Service'];
        }

        return [
            'cleared' => $response->successful(),
            'status' => $response->status(),
            'response' => $response->json(),
        ];
    }

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

            return ['updated' => false, 'status' => null, 'message' => 'Khong ket noi duoc Order Service'];
        }

        return [
            'updated' => $response->successful(),
            'status' => $response->status(),
            'response' => $response->json(),
        ];
    }

    private function request(): PendingRequest
    {
        $token = trim((string) config('services.internal.token'));

        if ($token === '') {
            throw new RuntimeException('INTERNAL_SERVICE_TOKEN chua duoc cau hinh');
        }

        return Http::acceptJson()
            ->withHeaders(['X-Internal-Service-Token' => $token])
            ->connectTimeout((int) config('services.connect_timeout', 2))
            ->timeout((int) config('services.timeout', 5))
            ->retry(2, 100, null, false);
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim((string) config('services.order.url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('ORDER_SERVICE_URL chua duoc cau hinh');
        }

        return $baseUrl.$path;
    }
}
