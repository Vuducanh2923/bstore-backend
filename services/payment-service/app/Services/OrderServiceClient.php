<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderServiceClient
{
    public function markPaymentPaid(int $orderId, ?string $authorization = null): array
    {
        $baseUrl = rtrim((string) config('services.order.url'), '/');

        if ($baseUrl === '') {
            return [
                'updated' => false,
                'status' => null,
                'message' => 'ORDER_SERVICE_URL chua duoc cau hinh',
            ];
        }

        $url = "{$baseUrl}/api/internal/orders/{$orderId}/payment-status";
        $headers = [];

        if ($authorization) {
            $headers['Authorization'] = $authorization;
        }

        $payload = [
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'payment_method' => 'vnpay',
            'paid_at' => now()->toDateTimeString(),
        ];

        Log::info('payment.order_payment_paid.request', [
            'order_id' => $orderId,
            'order_service_url' => $baseUrl,
            'url' => $url,
            'payload' => $payload,
            'authorization_present' => $authorization !== null,
        ]);

        try {
            $response = Http::acceptJson()
                ->withHeaders($headers)
                ->timeout((int) config('services.timeout', 10))
                ->patch($url, $payload);
        } catch (ConnectionException $exception) {
            Log::error('payment.order_payment_paid.connection_failed', [
                'order_id' => $orderId,
                'order_service_url' => $baseUrl,
                'url' => $url,
                'payload' => $payload,
                'message' => $exception->getMessage(),
            ]);

            return [
                'updated' => false,
                'status' => null,
                'message' => 'Khong ket noi duoc Order Service de cap nhat payment_status=paid',
            ];
        }

        $body = $response->json();

        Log::info('payment.order_payment_paid.response', [
            'order_id' => $orderId,
            'order_service_url' => $baseUrl,
            'url' => $url,
            'payload' => $payload,
            'status' => $response->status(),
            'response' => $body,
        ]);

        return [
            'updated' => $response->successful(),
            'status' => $response->status(),
            'message' => $response->successful()
                ? 'Da cap nhat payment_status=paid cho don hang'
                : 'Order Service tra ve loi khi cap nhat payment_status=paid',
            'response' => $body,
        ];
    }

    public function clearCartForPaidOrder(int $orderId): array
    {
        $baseUrl = rtrim((string) config('services.order.url'), '/');

        if ($baseUrl === '') {
            return [
                'cleared' => false,
                'status' => null,
                'message' => 'ORDER_SERVICE_URL chua duoc cau hinh',
            ];
        }

        $url = "{$baseUrl}/api/internal/orders/{$orderId}/cart/clear";
        $payload = [
            'source' => 'payment-service',
            'reason' => 'vnpay_paid',
        ];

        Log::info('payment.order_cart_clear.request', [
            'order_id' => $orderId,
            'url' => $url,
            'payload' => $payload,
        ]);

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.timeout', 10))
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            Log::error('payment.order_cart_clear.connection_failed', [
                'order_id' => $orderId,
                'url' => $url,
                'payload' => $payload,
                'message' => $exception->getMessage(),
            ]);

            return [
                'cleared' => false,
                'status' => null,
                'message' => 'Khong ket noi duoc Order Service de xoa gio hang',
            ];
        }

        $body = $response->json();

        Log::info('payment.order_cart_clear.response', [
            'order_id' => $orderId,
            'url' => $url,
            'payload' => $payload,
            'status' => $response->status(),
            'response' => $body,
        ]);

        return [
            'cleared' => $response->successful(),
            'status' => $response->status(),
            'message' => $response->successful()
                ? 'Da xoa gio hang sau khi thanh toan thanh cong'
                : 'Order Service tra ve loi khi xoa gio hang',
            'response' => $body,
        ];
    }

    public function markPaymentFailed(int $orderId, string $reason, ?string $authorization = null): array
    {
        $baseUrl = rtrim((string) config('services.order.url'), '/');

        if ($baseUrl === '') {
            return [
                'updated' => false,
                'status' => null,
                'message' => 'ORDER_SERVICE_URL chua duoc cau hinh',
            ];
        }

        $url = "{$baseUrl}/api/orders/{$orderId}";
        $headers = [];

        if ($authorization) {
            $headers['Authorization'] = $authorization;
        }

        $payload = [
            'payment_status' => 'failed',
            'cancel_reason' => $reason,
        ];

        Log::info('payment.order_payment_failed.request', [
            'order_id' => $orderId,
            'url' => $url,
            'payload' => $payload,
            'authorization_present' => $authorization !== null,
        ]);

        try {
            $response = Http::acceptJson()
                ->withHeaders($headers)
                ->timeout((int) config('services.timeout', 10))
                ->patch($url, $payload);
        } catch (ConnectionException $exception) {
            Log::error('payment.order_payment_failed.connection_failed', [
                'order_id' => $orderId,
                'url' => $url,
                'payload' => $payload,
                'message' => $exception->getMessage(),
            ]);

            return [
                'updated' => false,
                'status' => null,
                'message' => 'Khong ket noi duoc Order Service de cap nhat payment_status',
            ];
        }

        $body = $response->json();

        Log::info('payment.order_payment_failed.response', [
            'order_id' => $orderId,
            'url' => $url,
            'payload' => $payload,
            'status' => $response->status(),
            'response' => $body,
        ]);

        return [
            'updated' => $response->successful(),
            'status' => $response->status(),
            'message' => $response->successful()
                ? 'Da cap nhat payment_status=failed cho don hang'
                : 'Order Service tra ve loi khi cap nhat payment_status',
            'response' => $body,
        ];
    }
}
