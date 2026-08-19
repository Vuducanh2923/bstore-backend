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

    // Thực hiện hoàn tiền.
    public function refund(int $orderId, int $refundId, float $amount, string $reason, int $requestedBy): string
    {
        $baseUrl = rtrim((string) config('services.payment.url'), '/');

        if ($baseUrl === '' || (string) config('services.internal.token') === '') {
            throw new HttpException(503, 'Dịch vụ thanh toán chưa được cấu hình an toàn');
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

            throw new HttpException(503, 'Không thể kết nối dịch vụ thanh toán', $exception);
        }

        if ($response->status() === 409 || $response->status() === 422) {
            throw ValidationException::withMessages([
                'refund' => [(string) ($response->json('message') ?: 'Nhà cung cấp từ chối hoàn tiền')],
            ]);
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::error('payment.refund.failed', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new HttpException(502, 'Dịch vụ thanh toán không thể hoàn tiền');
        }

        $status = strtolower((string) $response->json('data.status'));

        if (! in_array($status, ['refunded', 'processing'], true)) {
            throw new HttpException(502, 'Dịch vụ thanh toán trả về trạng thái hoàn tiền không hợp lệ');
        }

        return $status;
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
