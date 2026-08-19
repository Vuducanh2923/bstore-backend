<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentRefund;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class VnpayRefundService
{
    private const VERSION = '2.1.0';

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly PaymentService $payments) {}

    // Thực hiện hoàn tiền.
    public function refund(
        int $orderId,
        float|int|string $amount,
        string $reason,
        string $requestedBy,
        string $idempotencyKey,
        string $ipAddress,
    ): PaymentRefund {
        $amount = round((float) $amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Số tiền hoàn phải lớn hơn 0');
        }

        $existing = PaymentRefund::query()->where('request_id', $idempotencyKey)->first();

        if ($existing) {
            if ((int) $existing->order_id !== $orderId || abs((float) $existing->amount - $amount) > 0.001) {
                throw new RuntimeException('Idempotency key đã được sử dụng cho yêu cầu khác');
            }

            if (in_array($existing->status, ['refunded', 'processing'], true)) {
                return $existing;
            }
        }

        $payment = $this->payments->paidVnpayPaymentForOrder($orderId);

        if (! $payment) {
            throw new RuntimeException('Không tìm thấy giao dịch VNPAY đã thanh toán');
        }

        $refundable = (float) $payment->amount - $this->payments->refundedAmount($payment);

        if ($amount > $refundable + 0.001) {
            throw new RuntimeException('Số tiền hoàn vượt quá số tiền còn có thể hoàn');
        }

        $transactionType = abs($amount - (float) $payment->amount) < 0.001 ? '02' : '03';
        $request = $this->requestPayload($payment, $amount, $transactionType, $reason, $requestedBy, $idempotencyKey, $ipAddress);

        $refund = DB::connection('bstore_payment')->transaction(function () use (
            $existing,
            $payment,
            $orderId,
            $idempotencyKey,
            $amount,
            $transactionType,
            $reason,
            $requestedBy,
            $request,
        ) {
            $attributes = [
                'payment_id' => $payment->id,
                'order_id' => $orderId,
                'request_id' => $idempotencyKey,
                'amount' => $amount,
                'transaction_type' => $transactionType,
                'status' => 'pending',
                'reason' => $reason,
                'requested_by' => $requestedBy,
                'request_data' => $request,
                'requested_at' => now(),
            ];

            if ($existing) {
                $existing->fill($attributes)->save();

                return $existing;
            }

            return PaymentRefund::create($attributes);
        });

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout((int) config('services.connect_timeout', 2))
                ->timeout((int) config('services.timeout', 10))
                ->post($this->config('refund_url'), $request);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Không kết nối được VNPAY để hoàn tiền', previous: $exception);
        }

        $body = $response->json();

        if (! $response->successful() || ! is_array($body)) {
            throw new RuntimeException('VNPAY trả về phản hồi hoàn tiền không hợp lệ');
        }

        if (! $this->verifyResponse($body)) {
            $this->updateRefund($refund, $body, 'failed');
            throw new RuntimeException('Chữ ký phản hồi hoàn tiền VNPAY không hợp lệ');
        }

        if ((string) ($body['vnp_TmnCode'] ?? '') !== $request['vnp_TmnCode']
            || (string) ($body['vnp_TxnRef'] ?? '') !== $request['vnp_TxnRef']
            || (int) ($body['vnp_Amount'] ?? -1) !== $request['vnp_Amount']
            || (string) ($body['vnp_TransactionType'] ?? '') !== $request['vnp_TransactionType']) {
            $this->updateRefund($refund, $body, 'failed');
            throw new RuntimeException('Phản hồi hoàn tiền VNPAY không khớp yêu cầu');
        }

        $responseCode = (string) ($body['vnp_ResponseCode'] ?? '');
        $transactionStatus = (string) ($body['vnp_TransactionStatus'] ?? '');
        $status = match (true) {
            $responseCode === '00' && $transactionStatus === '00' => 'refunded',
            $responseCode === '94',
            $responseCode === '00' && in_array($transactionStatus, ['05', '06'], true) => 'processing',
            default => 'failed',
        };

        $refund = $this->updateRefund($refund, $body, $status);

        if ($status === 'failed') {
            throw new RuntimeException('VNPAY từ chối hoàn tiền: '.($body['vnp_Message'] ?? $responseCode));
        }

        if ($status === 'refunded') {
            $this->syncPaymentRefundStatus($payment);
        }

        return $refund;
    }

    // Thực hiện yêu cầu dữ liệu gửi.
    private function requestPayload(
        Payment $payment,
        float $amount,
        string $transactionType,
        string $reason,
        string $requestedBy,
        string $idempotencyKey,
        string $ipAddress,
    ): array {
        $timezone = $this->config('timezone', 'Asia/Ho_Chi_Minh');
        $createDate = Carbon::now($timezone)->format('YmdHis');
        $original = $this->originalTransactionData($payment);
        $requestId = 'RF'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 28));
        $payload = [
            'vnp_RequestId' => $requestId,
            'vnp_Version' => self::VERSION,
            'vnp_Command' => 'refund',
            'vnp_TmnCode' => $this->config('tmn_code'),
            'vnp_TransactionType' => $transactionType,
            'vnp_TxnRef' => (string) $payment->transaction_code,
            'vnp_Amount' => (int) round($amount * 100),
            'vnp_TransactionNo' => (string) ($original['vnp_TransactionNo'] ?? ''),
            'vnp_TransactionDate' => (string) ($original['vnp_CreateDate'] ?? $payment->paid_at?->timezone($timezone)->format('YmdHis') ?? $createDate),
            'vnp_CreateBy' => $this->vnpayText($requestedBy, 245),
            'vnp_CreateDate' => $createDate,
            'vnp_IpAddr' => $ipAddress,
            'vnp_OrderInfo' => $this->vnpayText($reason, 255),
        ];

        $payload['vnp_SecureHash'] = hash_hmac('sha512', implode('|', array_values($payload)), $this->config('hash_secret'));

        return $payload;
    }

    // Thực hiện gốc giao dịch dữ liệu.
    private function originalTransactionData(Payment $payment): array
    {
        $transactions = $payment->transactions->sortByDesc('id');
        $paid = $transactions->first(fn ($transaction) => $transaction->status === 'paid');
        $created = $transactions->first(fn ($transaction) => data_get($transaction->response_data, 'event') === 'create_payment_url');

        return array_merge($created?->response_data ?? [], $paid?->response_data ?? []);
    }

    // Thực hiện xác minh phản hồi.
    private function verifyResponse(array $body): bool
    {
        $received = (string) ($body['vnp_SecureHash'] ?? '');
        $fields = [
            'vnp_ResponseId', 'vnp_Command', 'vnp_ResponseCode', 'vnp_Message', 'vnp_TmnCode',
            'vnp_TxnRef', 'vnp_Amount', 'vnp_BankCode', 'vnp_PayDate', 'vnp_TransactionNo',
            'vnp_TransactionType', 'vnp_TransactionStatus', 'vnp_OrderInfo',
        ];
        $data = implode('|', array_map(fn (string $field) => (string) ($body[$field] ?? ''), $fields));
        $calculated = hash_hmac('sha512', $data, $this->config('hash_secret'));

        return $received !== '' && hash_equals($received, $calculated);
    }

    // Cập nhật hoàn tiền.
    private function updateRefund(PaymentRefund $refund, array $body, string $status): PaymentRefund
    {
        $refund->fill([
            'provider_refund_id' => $body['vnp_TransactionNo'] ?? null,
            'status' => $status,
            'response_code' => $body['vnp_ResponseCode'] ?? null,
            'transaction_status' => $body['vnp_TransactionStatus'] ?? null,
            'response_data' => $body,
            'completed_at' => $status === 'refunded' ? now() : null,
        ])->save();

        return $refund->fresh() ?? $refund;
    }

    // Thực hiện đồng bộ thanh toán hoàn tiền trạng thái.
    private function syncPaymentRefundStatus(Payment $payment): void
    {
        $refunded = (float) PaymentRefund::query()
            ->where('payment_id', $payment->id)
            ->where('status', 'refunded')
            ->sum('amount');

        $payment->status = $refunded + 0.001 >= (float) $payment->amount ? 'refunded' : 'partially_refunded';
        $payment->save();
    }

    // Thực hiện cấu hình.
    private function config(string $key, ?string $default = null): string
    {
        $value = trim((string) config("services.vnpay.{$key}", $default));

        if ($value === '' && in_array($key, ['tmn_code', 'hash_secret', 'refund_url'], true)) {
            throw new RuntimeException("Cấu hình VNPAY {$key} chưa được thiết lập");
        }

        return $value;
    }

    // Thực hiện vnpay văn bản.
    private function vnpayText(string $value, int $maxLength): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9 ._-]/', '', Str::ascii($value)) ?: 'BStore';

        return substr($ascii, 0, $maxLength);
    }
}
