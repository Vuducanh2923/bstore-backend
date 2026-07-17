<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentRefundService
{
    public function refund(int $orderId, int $refundId, float $amount, string $reason, int $requestedBy): string
    {
        $baseUrl = rtrim((string) config('services.payment.url'), '/');

        if ($baseUrl === '' || (string) config('services.internal.token') === '') {
            throw new HttpException(503, 'Dich vu thanh toan chua duoc cau hinh an toan');
        }

        try {
            $response = $this->request()->post("{$baseUrl}/api/internal/payments/{$orderId}/refunds", [
                'amount' => $amount,
                'reason' => $reason,
                'requested_by' => "user{$requestedBy}",
                'idempotency_key' => "refund-{$refundId}",
            ]);
        } catch (ConnectionException $exception) {
            Log::error('payment.refund.connection_failed', [
                'order_id' => $orderId,
                'error' => $exception->getMessage(),
            ]);

            throw new HttpException(503, 'Khong the ket noi dich vu thanh toan', $exception);
        }

        if ($response->status() === 409 || $response->status() === 422) {
            throw ValidationException::withMessages([
                'refund' => [(string) ($response->json('message') ?: 'Nha cung cap tu choi hoan tien')],
            ]);
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::error('payment.refund.failed', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new HttpException(502, 'Dich vu thanh toan khong the hoan tien');
        }

        $status = strtolower((string) $response->json('data.status'));

        if (! in_array($status, ['refunded', 'processing'], true)) {
            throw new HttpException(502, 'Dich vu thanh toan tra ve trang thai hoan tien khong hop le');
        }

        return $status;
    }

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
