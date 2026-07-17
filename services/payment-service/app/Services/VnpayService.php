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

    public function __construct(
        private readonly PaymentService $payments,
        private readonly OrderServiceClient $orders,
    ) {}

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

    /** The browser return URL is display-only; it never changes local state. */
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

        return [
            'verified' => true,
            'successful' => $payment->status === 'paid',
            'provider_successful' => $this->isSuccessfulPayment($payload),
            'payment_status' => $payment->status,
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
            $orderUpdate = $this->orders->markPaymentFailed((int) $payment->order_id, 'VNPAY tu choi hoac giao dich khong thanh cong');

            return ($orderUpdate['updated'] ?? false) ? $this->ipnResult('00') : $this->ipnResult('99');
        }

        $orderUpdate = $this->orders->markPaymentPaid((int) $payment->order_id);

        if (! ($orderUpdate['updated'] ?? false)) {
            $this->payments->recordVnpaySyncPending($payment, $payload);

            return $this->ipnResult('99');
        }

        $this->payments->recordVnpayPaid($payment, $payload);
        $cartClear = $this->orders->clearCartForPaidOrder((int) $payment->order_id);

        if (! ($cartClear['cleared'] ?? false)) {
            Log::warning('vnpay.ipn.cart_sync_pending', ['payment_id' => $payment->id, 'order_id' => $payment->order_id]);

            return $this->ipnResult('99');
        }

        return $this->ipnResult('00');
    }

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

    private function hashData(array $params): string
    {
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);
        ksort($params);

        return collect($params)
            ->map(fn ($value, string $key) => urlencode($key).'='.urlencode(is_array($value) ? implode(',', $value) : (string) $value))
            ->implode('&');
    }

    private function secureHash(string $data): string
    {
        return hash_hmac('sha512', $data, $this->config('hash_secret'));
    }

    private function isSuccessfulPayment(array $payload): bool
    {
        return (string) ($payload['vnp_ResponseCode'] ?? '') === '00'
            && (string) ($payload['vnp_TransactionStatus'] ?? '') === '00';
    }

    private function amountMatches(Payment $payment, array $payload): bool
    {
        return isset($payload['vnp_Amount'])
            && is_numeric($payload['vnp_Amount'])
            && (int) $payload['vnp_Amount'] === $this->toVnpayAmount($payment->amount);
    }

    private function toVnpayAmount(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

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

    private function ipnResult(string $code): array
    {
        return ['response' => match ($code) {
            '00' => ['RspCode' => '00', 'Message' => 'Confirm Success'],
            '01' => ['RspCode' => '01', 'Message' => 'Order not found'],
            '04' => ['RspCode' => '04', 'Message' => 'Invalid amount'],
            '97' => ['RspCode' => '97', 'Message' => 'Invalid signature'],
            default => ['RspCode' => '99', 'Message' => 'Unknown error'],
        }];
    }

    private function ensureConfigured(): void
    {
        $missing = collect(self::REQUIRED_CONFIG)
            ->filter(fn (string $env, string $key) => $this->config($key) === '')
            ->values()
            ->all();

        if ($missing !== []) {
            throw new RuntimeException('Cau hinh VNPAY chua day du: '.implode(', ', $missing));
        }
    }

    private function config(string $key, ?string $default = null): string
    {
        return trim((string) config("services.vnpay.{$key}", $default));
    }
}
