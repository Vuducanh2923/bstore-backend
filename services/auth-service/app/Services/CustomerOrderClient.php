<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CustomerOrderClient
{

    // Thực hiện đơn hàng cho khách hàng.
    public function ordersForCustomer(int $userId): array
    {
        $baseUrl = rtrim((string) config('services.order.url'), '/');

        try {
            $response = $this->request()
                ->get("{$baseUrl}/api/internal/customers/{$userId}/orders");
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Dịch vụ đơn hàng không khả dụng', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('Không lấy được lịch sử mua hàng');
        }

        $payload = $response->json();

        return is_array($payload) && is_array($payload['data'] ?? null)
            ? $payload['data']
            : [];
    }

    // Thực hiện yêu cầu.
    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders([
                'X-Internal-Service-Token' => (string) config('services.internal_service_token'),
            ])
            ->connectTimeout((int) config('services.connect_timeout', 2))
            ->timeout((int) config('services.timeout', 5))
            ->retry(2, 100, null, false);
    }
}
