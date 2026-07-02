<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CustomerOrderClient
{
    public function ordersForCustomer(int $userId): array
    {
        $baseUrl = rtrim((string) config('services.order.url'), '/');

        try {
            $response = $this->request()
                ->get("{$baseUrl}/api/internal/customers/{$userId}/orders");
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Order Service khong kha dung', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('Khong lay duoc lich su mua hang');
        }

        $payload = $response->json();

        return is_array($payload) && is_array($payload['data'] ?? null)
            ? $payload['data']
            : [];
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout((int) config('services.connect_timeout', 2))
            ->timeout((int) config('services.timeout', 5))
            ->retry(2, 100, null, false);
    }
}
