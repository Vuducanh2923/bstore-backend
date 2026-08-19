<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentStatusClient
{

    // Thực hiện synchronize.
    public function synchronize(Order $order, string $status): array
    {
        $baseUrl = rtrim((string) config('services.payment.url'), '/');

        if ($baseUrl === '' || (string) config('services.internal.token') === '') {
            throw new HttpException(503, 'Dịch vụ thanh toán chưa được cấu hình an toàn');
        }

        try {
            $response = $this->request()->patch("{$baseUrl}/api/internal/orders/{$order->id}/payment-status", [
                'payment_status' => $status,
                'payment_method' => strtolower((string) $order->getAttribute('payment_method')),
                'amount' => (float) $order->final_amount,
            ]);
        } catch (ConnectionException $exception) {
            Log::error('payment.status_sync.connection_failed', [
                'order_id' => $order->id,
                'payment_status' => $status,
                'error' => $exception->getMessage(),
            ]);

            throw new HttpException(503, 'Không thể kết nối dịch vụ thanh toán', $exception);
        }

        if ($response->status() === 422) {
            throw ValidationException::withMessages([
                'payment_status' => [(string) ($response->json('message') ?: 'Trạng thái thanh toán không hợp lệ')],
            ]);
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::error('payment.status_sync.failed', [
                'order_id' => $order->id,
                'payment_status' => $status,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new HttpException(502, 'Dịch vụ thanh toán không thể cập nhật trạng thái');
        }

        $data = (array) $response->json('data');

        if (strtolower((string) ($data['status'] ?? '')) !== $status) {
            throw new HttpException(502, 'Dịch vụ thanh toán trả về trạng thái không đồng bộ');
        }

        return $data;
    }

    // Thực hiện yêu cầu.
    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeader(
                (string) config('services.internal.header', 'X-Internal-Service-Token'),
                (string) config('services.internal.token'),
            )
            ->connectTimeout((int) config('services.connect_timeout', 2))
            ->timeout((int) config('services.timeout', 5));
    }
}
