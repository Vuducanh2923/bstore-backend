<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VnpayService
{
    private const CONFIG_ENV_KEYS = [
        'tmn_code' => 'VNPAY_TMN_CODE',
        'hash_secret' => 'VNPAY_HASH_SECRET',
        'payment_url' => 'VNPAY_PAYMENT_URL',
        'return_url' => 'VNPAY_RETURN_URL',
        'ipn_url' => 'VNPAY_IPN_URL',
    ];

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderServiceClient $orderServiceClient,
    ) {}

    public function createPaymentUrl(array $data, string $ipAddress): array
    {
        $this->ensureConfigured();
        $orderInfo = (string) ($data['order_info'] ?? "Thanh toan don hang {$data['order_id']}");

        $payment = $this->paymentService->createPendingVnpayPayment(
            (int) $data['order_id'],
            $data['amount'],
            $orderInfo,
        );

        Log::info('vnpay.payment_created', [
            'payload' => $data,
            'payment' => $payment->toArray(),
        ]);

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $this->configValue('tmn_code'),
            'vnp_Amount' => $this->toVnpayAmount($data['amount']),
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => (string) $payment->transaction_code,
            'vnp_OrderInfo' => $orderInfo,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => $this->configValue('return_url'),
            'vnp_IpAddr' => $ipAddress,
            'vnp_CreateDate' => Carbon::now($this->configValue('timezone', 'Asia/Ho_Chi_Minh'))->format('YmdHis'),
        ];

        $signedUrl = $this->signedPaymentUrl($params);
        $paymentUrl = $signedUrl['payment_url'];

        Log::info('vnpay.payment_url_created', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'vnp_TmnCode' => $params['vnp_TmnCode'],
            'hashData' => $signedUrl['hash_data'],
            'secureHash' => $signedUrl['secure_hash'],
            'paymentUrl' => $paymentUrl,
        ]);

        $response = [
            'payment_url' => $paymentUrl,
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'amount' => $payment->amount,
            'txn_ref' => $payment->transaction_code,
            'return_url' => $this->configValue('return_url'),
            'ipn_url' => $this->configValue('ipn_url'),
            'vnpay_params' => $params,
        ];

        Log::info('vnpay.create_payment_url', [
            'request' => $data,
            'response' => $response,
        ]);

        return $response;
    }

    public function handleReturn(array $payload): array
    {
        return $this->processCallback('return', $payload);
    }

    public function handleIpn(array $payload): array
    {
        $result = $this->processCallback('ipn', $payload);
        $result['response'] = $this->ipnResponse($result['code']);

        Log::info('vnpay.ipn.response', [
            'request' => $payload,
            'response' => $result['response'],
            'result' => $result,
        ]);

        return $result;
    }

    public function verifySignature(array $payload): bool
    {
        return $this->signatureContext($payload)['valid'];
    }

    private function processCallback(string $source, array $payload): array
    {
        $this->ensureConfigured();

        $signature = $this->signatureContext($payload);
        $verified = $signature['valid'];
        $txnRef = (string) ($payload['vnp_TxnRef'] ?? '');
        $successful = $this->isSuccessfulPayment($payload);

        $result = [
            'code' => '00',
            'source' => $source,
            'verified' => $verified,
            'successful' => $successful,
            'already_confirmed' => false,
            'payment_status' => null,
            'message' => 'Da xu ly ket qua VNPAY',
            'payment' => null,
            'order_updated' => false,
            'order_update' => null,
            'cart_clear' => null,
            'vnpay' => $payload,
        ];

        Log::info("vnpay.{$source}.request", [
            'source' => $source,
            'request' => $payload,
            'verified' => $verified,
        ]);

        Log::info("vnpay.{$source}.signature_debug", [
            'source' => $source,
            'received_secureHash' => $signature['received_secure_hash'],
            'calculated_secureHash' => $signature['calculated_secure_hash'],
            'hashData' => $signature['hash_data'],
            'params_without_secure_hash' => $signature['params'],
            'verified' => $verified,
        ]);

        if (! $verified) {
            return $this->callbackResult($source, $payload, $result, [
                'code' => '97',
                'message' => 'Chu ky VNPAY khong hop le',
            ]);
        }

        if ($txnRef === '') {
            return $this->callbackResult($source, $payload, $result, [
                'code' => '01',
                'message' => 'Thieu ma giao dich VNPAY',
            ]);
        }

        $payment = $this->paymentService->paymentForVnpayTxnRef($txnRef);

        if (! $payment) {
            return $this->callbackResult($source, $payload, $result, [
                'code' => '01',
                'message' => 'Khong tim thay payment tu VNPAY txn_ref',
            ]);
        }

        $alreadyConfirmed = $payment->status === 'paid';

        if (! $alreadyConfirmed && ! $this->amountMatches($payment, $payload)) {
            return $this->callbackResult($source, $payload, $result, [
                'code' => '04',
                'message' => 'So tien VNPAY khong khop payment',
                'payment' => $this->paymentData($payment),
            ]);
        }

        if ($alreadyConfirmed) {
            $successful = true;
            $payment = $payment->fresh(['transactions', 'invoices']) ?? $payment;
        } else {
            $payment = $this->paymentService->recordVnpayCallback($payment, $payload, $successful);
        }

        $orderUpdate = null;
        $cartClear = null;

        if ($successful && $payment->status === 'paid') {
            $orderUpdate = $this->orderServiceClient->markPaymentPaid((int) $payment->order_id);

            Log::info('vnpay.order_payment_paid_sync', [
                'source' => $source,
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'payment_status' => $payment->status,
                'order_service_url' => config('services.order.url'),
                'response' => $orderUpdate,
            ]);

            $cartClear = $this->orderServiceClient->clearCartForPaidOrder((int) $payment->order_id);
        }

        $result = array_merge($result, [
            'successful' => $successful,
            'already_confirmed' => $alreadyConfirmed,
            'payment_status' => $payment->status,
            'message' => $alreadyConfirmed
                ? 'Thanh toán đã được xác nhận trước đó'
                : ($successful ? 'Thanh toan VNPAY thanh cong' : 'Thanh toan VNPAY that bai'),
            'payment' => $this->paymentData($payment),
            'order_updated' => (bool) ($orderUpdate['updated'] ?? false),
            'order_update' => $orderUpdate,
            'cart_clear' => $cartClear,
        ]);

        return $this->callbackResult($source, $payload, $result);
    }

    private function callbackResult(string $source, array $payload, array $result, array $overrides = []): array
    {
        $result = array_merge($result, $overrides);

        Log::info("vnpay.{$source}.processed", [
            'request' => $payload,
            'response' => $result,
        ]);

        return $result;
    }

    private function signedPaymentUrl(array $params): array
    {
        $hashData = $this->hashData($params);
        $query = $this->queryString($params);
        $secureHash = $this->secureHash($hashData);

        return [
            'hash_data' => $hashData,
            'secure_hash' => $secureHash,
            'payment_url' => rtrim($this->configValue('payment_url'), '?').'?'.$query.'&vnp_SecureHash='.$secureHash,
        ];
    }

    private function secureHash(string $hashData): string
    {
        return hash_hmac('sha512', $hashData, $this->configValue('hash_secret'));
    }

    private function signatureContext(array $payload): array
    {
        $secureHash = (string) ($payload['vnp_SecureHash'] ?? '');
        $params = $this->callbackParamsForHash($payload);
        $hashData = $this->hashData($params);
        $calculatedHash = $this->secureHash($hashData);

        return [
            'received_secure_hash' => $secureHash,
            'calculated_secure_hash' => $calculatedHash,
            'hash_data' => $hashData,
            'params' => $params,
            'valid' => $secureHash !== '' && hash_equals($secureHash, $calculatedHash),
        ];
    }

    private function hashData(array $params): string
    {
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);
        ksort($params);

        return collect($params)
            ->map(fn ($value, string $key) => urlencode($key).'='.urlencode((string) ($value ?? '')))
            ->implode('&');
    }

    private function queryString(array $params): string
    {
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);
        ksort($params);

        return collect($params)
            ->map(fn ($value, string $key) => urlencode($key).'='.urlencode((string) ($value ?? '')))
            ->implode('&');
    }

    private function callbackParamsForHash(array $payload): array
    {
        unset($payload['vnp_SecureHash'], $payload['vnp_SecureHashType']);
        ksort($payload);

        return collect($payload)
            ->map(fn ($value) => is_array($value) ? implode(',', $value) : (string) $value)
            ->all();
    }

    private function isSuccessfulPayment(array $payload): bool
    {
        return (string) ($payload['vnp_ResponseCode'] ?? '') === '00'
            && (string) ($payload['vnp_TransactionStatus'] ?? '') === '00';
    }

    private function amountMatches(Payment $payment, array $payload): bool
    {
        if (! isset($payload['vnp_Amount']) || ! is_numeric($payload['vnp_Amount'])) {
            return false;
        }

        return (int) $payload['vnp_Amount'] === $this->toVnpayAmount($payment->amount);
    }

    private function toVnpayAmount(float|int|string $amount): int
    {
        return ((int) $amount) * 100;
    }

    private function paymentData(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'payment_method' => $payment->payment_method,
            'payment_provider' => $payment->payment_provider,
            'transaction_code' => $payment->transaction_code,
            'amount' => $payment->amount,
            'status' => $payment->status,
            'paid_at' => $payment->paid_at?->toJSON(),
        ];
    }

    private function ipnResponse(string $code): array
    {
        return match ($code) {
            '00' => ['RspCode' => '00', 'Message' => 'Confirm Success'],
            '01' => ['RspCode' => '01', 'Message' => 'Order not found'],
            '04' => ['RspCode' => '04', 'Message' => 'Invalid amount'],
            '97' => ['RspCode' => '97', 'Message' => 'Invalid signature'],
            default => ['RspCode' => '99', 'Message' => 'Unknown error'],
        };
    }

    private function ensureConfigured(): void
    {
        $missing = [];

        foreach (self::CONFIG_ENV_KEYS as $configKey => $envKey) {
            if ($this->configValue($configKey) === '') {
                $missing[] = $envKey;
            }
        }

        if ($missing === []) {
            return;
        }

        Log::error('vnpay.config_missing', ['missing' => $missing]);

        throw new RuntimeException('Cau hinh VNPAY chua day du: '.implode(', ', $missing));
    }

    private function configValue(string $key, ?string $default = null): string
    {
        return trim((string) config("services.vnpay.{$key}", $default));
    }
}
