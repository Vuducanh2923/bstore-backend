<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VnpayService
{
    private const REQUIRED_CONFIG = [
        'tmn_code' => 'VNPAY_TMN_CODE',
        'hash_secret' => 'VNPAY_HASH_SECRET',
        'payment_url' => 'VNPAY_PAYMENT_URL',
        'return_url' => 'VNPAY_RETURN_URL',
        'ipn_url' => 'VNPAY_IPN_URL',
    ];

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(

        private readonly PaymentService $payments,
        private readonly OrderServiceClient $orders,
    ) {}

    // Tạo hoặc lưu thanh toán url.
    public function createPaymentUrl(int $orderId, float|int|string $amount, string $orderInfo, string $ipAddress): array
    {
        $this->ensureConfigured();
        $createDate = Carbon::now($this->config('timezone', 'Asia/Ho_Chi_Minh'))->format('YmdHis');
        $payment = $this->payments->createOrReusePendingVnpayPayment($orderId, $amount, $orderInfo, $createDate);
        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $this->config('tmn_code'),
            'vnp_Amount' => $this->toVnpayAmount($payment->amount),
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => (string) $payment->transaction_code,
            'vnp_OrderInfo' => $orderInfo,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => $this->config('return_url'),
            'vnp_IpAddr' => $ipAddress,
            'vnp_CreateDate' => $createDate,
        ];

        $hashData = $this->hashData($params);
        $paymentUrl = rtrim($this->config('payment_url'), '?').'?'.$hashData.'&vnp_SecureHash='.$this->secureHash($hashData);

        return [
            'payment_url' => $paymentUrl,
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'amount' => $payment->amount,
            'txn_ref' => $payment->transaction_code,
        ];
    }

    /** A signed browser return is also a safe fallback when VNPAY cannot reach the configured IPN URL. */
    public function handleReturn(array $payload): array
    {
        $this->ensureConfigured();

        if (! $this->verifySignature($payload)) {
            return ['verified' => false, 'successful' => false, 'payment_status' => null, 'payment' => null];
        }

        $payment = $this->payments->paymentForVnpayTxnRef((string) ($payload['vnp_TxnRef'] ?? ''));

        if (! $payment || ! $this->amountMatches($payment, $payload)) {
            return ['verified' => true, 'successful' => false, 'payment_status' => null, 'payment' => null];
        }

        $providerSuccessful = $this->isSuccessfulPayment($payload);
        $synchronized = false;

        if ($providerSuccessful) {
            $settlement = $this->settleSuccessfulPayment($payment, $payload);
            $payment = $settlement['payment'];
            $synchronized = $settlement['synchronized'];
        }

        return [
            'verified' => true,
            'successful' => $providerSuccessful && $synchronized && $payment->status === 'paid',
            'provider_successful' => $providerSuccessful,
            'payment_status' => $payment->status,
            'order_id' => $payment->order_id,
            'payment' => $this->paymentData($payment),
        ];
    }

    /** Only the authenticated VNPAY IPN is allowed to settle Payment, Order and Cart. */
    public function handleIpn(array $payload): array
    {
        $this->ensureConfigured();

        if (! $this->verifySignature($payload)) {
            return $this->ipnResult('97');
        }

        $txnRef = (string) ($payload['vnp_TxnRef'] ?? '');
        $payment = $txnRef === '' ? null : $this->payments->paymentForVnpayTxnRef($txnRef);

        if (! $payment) {
            return $this->ipnResult('01');
        }

        if (! $this->amountMatches($payment, $payload)) {
            return $this->ipnResult('04');
        }

        if (! $this->isSuccessfulPayment($payload)) {
            $this->payments->recordVnpayFailed($payment, $payload);
            $orderUpdate = $this->orders->markPaymentFailed((int) $payment->order_id, 'VNPAY từ chối hoặc giao dịch không thành công');

            return ($orderUpdate['updated'] ?? false) ? $this->ipnResult('00') : $this->ipnResult('99');
        }

        $settlement = $this->settleSuccessfulPayment($payment, $payload);

        return $this->ipnResult($settlement['synchronized'] ? '00' : '99');
    }

    // Thực hiện settle thành công thanh toán.
    private function settleSuccessfulPayment(Payment $payment, array $payload): array
    {
        $orderUpdate = $this->orders->markPaymentPaid((int) $payment->order_id);

        if (! ($orderUpdate['updated'] ?? false)) {
            $payment = $this->payments->recordVnpaySyncPending($payment, $payload);

            return ['synchronized' => false, 'payment' => $payment];
        }

        $payment = $this->payments->recordVnpayPaid($payment, $payload);
        $cartClear = $this->orders->clearCartForPaidOrder((int) $payment->order_id);

        if (! ($cartClear['cleared'] ?? false)) {
            Log::warning('vnpay.cart_sync_pending', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'source' => 'vnpay_callback',
            ]);

            return ['synchronized' => false, 'payment' => $payment];
        }

        return ['synchronized' => true, 'payment' => $payment];
    }

    // Thực hiện xác minh chữ ký.
    public function verifySignature(array $payload): bool
    {
        $received = (string) ($payload['vnp_SecureHash'] ?? '');

        if ((string) ($payload['vnp_TmnCode'] ?? '') !== $this->config('tmn_code')) {
            return false;
        }

        unset($payload['vnp_SecureHash'], $payload['vnp_SecureHashType']);
        $calculated = $this->secureHash($this->hashData($payload));

        return $received !== '' && hash_equals($received, $calculated);
    }

    // Thực hiện mã băm dữ liệu.
    private function hashData(array $params): string
    {
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);
        ksort($params);

        return collect($params)
            ->map(fn ($value, string $key) => urlencode($key).'='.urlencode(is_array($value) ? implode(',', $value) : (string) $value))
            ->implode('&');
    }

    // Thực hiện bảo mật mã băm.
    private function secureHash(string $data): string
    {
        return hash_hmac('sha512', $data, $this->config('hash_secret'));
    }

    // Kiểm tra thành công thanh toán.
    private function isSuccessfulPayment(array $payload): bool
    {
        return (string) ($payload['vnp_ResponseCode'] ?? '') === '00'
            && (string) ($payload['vnp_TransactionStatus'] ?? '') === '00';
    }

    // Thực hiện số tiền khớp.
    private function amountMatches(Payment $payment, array $payload): bool
    {
        return isset($payload['vnp_Amount'])
            && is_numeric($payload['vnp_Amount'])
            && (int) $payload['vnp_Amount'] === $this->toVnpayAmount($payment->amount);
    }

    // Thực hiện cho vnpay số tiền.
    private function toVnpayAmount(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    // Thực hiện thanh toán dữ liệu.
    private function paymentData(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'amount' => $payment->amount,
            'status' => $payment->status,
            'transaction_code' => $payment->transaction_code,
            'paid_at' => $payment->paid_at?->toJSON(),
        ];
    }

    // Thực hiện ipn kết quả.
    private function ipnResult(string $code): array
    {
        return ['response' => match ($code) {
            '00' => ['RspCode' => '00', 'Message' => 'Xác nhận thành công.'],
            '01' => ['RspCode' => '01', 'Message' => 'Không tìm thấy đơn hàng.'],
            '04' => ['RspCode' => '04', 'Message' => 'Số tiền không hợp lệ.'],
            '97' => ['RspCode' => '97', 'Message' => 'Chữ ký không hợp lệ.'],
            default => ['RspCode' => '99', 'Message' => 'Đã xảy ra lỗi hệ thống.'],
        }];
    }

    // Kiểm tra đã cấu hình.
    private function ensureConfigured(): void
    {
        $missing = collect(self::REQUIRED_CONFIG)
            ->filter(fn (string $env, string $key) => $this->config($key) === '')
            ->values()
            ->all();

        if ($missing !== []) {
            throw new RuntimeException('Cấu hình VNPAY chưa đầy đủ: '.implode(', ', $missing));
        }
    }

    // Thực hiện cấu hình.
    private function config(string $key, ?string $default = null): string
    {
        return trim((string) config("services.vnpay.{$key}", $default));
    }
}
