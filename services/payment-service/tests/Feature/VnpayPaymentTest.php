<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'database.connections.bstore_payment' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
        'auth.token_key' => 'payment-access-secret-at-least-32-bytes',
        'services.internal.token' => 'internal-secret',
        'services.order.url' => 'http://order.test',
        'services.vnpay' => [
            'tmn_code' => 'TESTTMN',
            'hash_secret' => 'test-secret',
            'payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'return_url' => 'http://localhost:5173/payment/vnpay-return',
            'ipn_url' => 'http://localhost:8000/api/payments/vnpay/ipn',
            'refund_url' => 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction',
            'refund_user' => 'bstore-system',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ],
    ]);

    DB::purge('bstore_payment');

    Schema::connection('bstore_payment')->create('payments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id')->unique();
        $table->string('payment_method', 50);
        $table->string('payment_provider', 50)->nullable();
        $table->string('transaction_code', 191)->nullable()->unique();
        $table->decimal('amount', 15, 2)->default(0);
        $table->string('status', 20)->default('pending');
        $table->dateTime('paid_at')->nullable();
    });
    Schema::connection('bstore_payment')->create('payment_transactions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('payment_id')->index();
        $table->string('transaction_code', 191);
        $table->string('provider', 100);
        $table->decimal('amount', 15, 2);
        $table->string('status', 20);
        $table->json('response_data')->nullable();
        $table->unique(['transaction_code', 'provider']);
    });
    Schema::connection('bstore_payment')->create('invoices', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('payment_id')->unique();
        $table->unsignedBigInteger('order_id')->unique();
        $table->string('invoice_code', 191)->unique();
        $table->decimal('total_amount', 15, 2);
        $table->dateTime('issued_at')->nullable();
    });
    Schema::connection('bstore_payment')->create('payment_refunds', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('payment_id');
        $table->unsignedBigInteger('order_id');
        $table->string('request_id', 64)->unique();
        $table->string('provider_refund_id', 191)->nullable();
        $table->decimal('amount', 15, 2);
        $table->string('transaction_type', 2);
        $table->string('status', 20);
        $table->string('response_code', 10)->nullable();
        $table->string('transaction_status', 10)->nullable();
        $table->string('reason', 255);
        $table->string('requested_by', 100);
        $table->json('request_data')->nullable();
        $table->json('response_data')->nullable();
        $table->dateTime('requested_at');
        $table->dateTime('completed_at')->nullable();
        $table->timestamps();
    });
});

afterEach(fn () => Carbon::setTestNow());

test('VNPAY URL uses the order amount and owner supplied by Order Service', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-13 10:30:40', 'Asia/Ho_Chi_Minh'));
    paymentFakeOrderContext(123, 7, 90000, 'vnpay');

    $response = $this->withToken(paymentAccessToken(7))->postJson('/api/payments/vnpay/create', [
        'order_id' => 123,
        'amount' => 1,
        'order_info' => 'Thanh toan don 123',
    ]);

    $response->assertCreated()->assertJsonPath('data.order_id', 123)->assertJsonPath('data.amount', '90000.00');
    parse_str((string) parse_url($response->json('data.payment_url'), PHP_URL_QUERY), $query);

    expect($query['vnp_Amount'])->toBe('9000000')
        ->and($query['vnp_TmnCode'])->toBe('TESTTMN')
        ->and($query['vnp_OrderInfo'])->toBe('Thanh toan don 123')
        ->and($query['vnp_CreateDate'])->toBe('20260713103040')
        ->and($query['vnp_SecureHash'])->toBe(paymentVnpayHash($query));

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/internal/orders/123/payment-context?customer_id=7'
        && $request->hasHeader('X-Internal-Service-Token', 'internal-secret'));
});

test('customer authentication and authoritative order context are mandatory', function () {
    $this->postJson('/api/payments/vnpay/create', ['order_id' => 123])->assertUnauthorized();

    Http::fake(['http://order.test/*' => Http::response(['success' => false, 'message' => 'Khong thuoc customer'], 403)]);
    $this->withToken(paymentAccessToken(7))->postJson('/api/payments/vnpay/create', ['order_id' => 123])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Khong thuoc customer');

    expect(DB::connection('bstore_payment')->table('payments')->count())->toBe(0);
});

test('COD amount is determined by Order Service and client controlled fields are ignored', function () {
    paymentFakeOrderContext(124, 7, 75000, 'cod');

    $this->withToken(paymentAccessToken(7))->postJson('/api/payments', [
        'order_id' => 124,
        'amount' => 1,
        'status' => 'paid',
    ])->assertCreated()
        ->assertJsonPath('data.amount', '75000.00')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.payment_method', 'cod');
});

test('a retry reuses one payment row and rotates the VNPAY transaction reference', function () {
    paymentFakeOrderContext(123, 7, 90000, 'vnpay');
    $first = $this->withToken(paymentAccessToken(7))->postJson('/api/payments/vnpay/create', ['order_id' => 123])->json('data.txn_ref');
    $second = $this->withToken(paymentAccessToken(7))->postJson('/api/payments/vnpay/create', ['order_id' => 123])->json('data.txn_ref');

    expect(DB::connection('bstore_payment')->table('payments')->count())->toBe(1)
        ->and($first)->not->toBe($second);
});

test('signed browser return settles payment order and cart when IPN is unreachable', function () {
    Http::fake([
        'http://order.test/api/internal/orders/123/payment-status' => Http::response(['success' => true, 'data' => ['updated' => true]]),
        'http://order.test/api/internal/orders/123/cart/clear' => Http::response(['success' => true, 'data' => ['deleted_items' => 2]]),
    ]);
    $paymentId = paymentCreateVnpayPayment('RETURN-ONLY', 50000);
    $payload = paymentSignedCallback('RETURN-ONLY', 50000, '777');

    $this->getJson('/api/payments/vnpay/return?'.http_build_query($payload))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.successful', true)
        ->assertJsonPath('data.provider_successful', true)
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.order_id', 123);

    expect(DB::connection('bstore_payment')->table('payments')->find($paymentId)->status)->toBe('paid');
    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/internal/orders/123/cart/clear');
});

test('IPN settles Order then Payment then Cart and is idempotent', function () {
    Http::fake([
        'http://order.test/api/internal/orders/123/payment-status' => Http::response(['success' => true, 'data' => ['updated' => true]]),
        'http://order.test/api/internal/orders/123/cart/clear' => Http::response(['success' => true, 'data' => ['deleted_items' => 2]]),
    ]);
    $paymentId = paymentCreateVnpayPayment('IPN-PAID', 50000);
    $payload = paymentSignedCallback('IPN-PAID', 50000, '909090');
    $query = http_build_query($payload);

    $this->getJson('/api/payments/vnpay/ipn?'.$query)->assertOk()->assertJson(['RspCode' => '00']);
    $this->getJson('/api/payments/vnpay/ipn?'.$query)->assertOk()->assertJson(['RspCode' => '00']);

    $payment = DB::connection('bstore_payment')->table('payments')->find($paymentId);
    expect($payment->status)->toBe('paid')
        ->and(DB::connection('bstore_payment')->table('invoices')->where('payment_id', $paymentId)->count())->toBe(1)
        ->and(DB::connection('bstore_payment')->table('payment_transactions')->where('transaction_code', '909090')->count())->toBe(1);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Internal-Service-Token', 'internal-secret'));
});

test('IPN remains retryable and does not mark Payment paid when Order synchronization fails', function () {
    Http::fake(['http://order.test/*' => Http::response(['success' => false], 503)]);
    $paymentId = paymentCreateVnpayPayment('IPN-RETRY', 50000);
    $payload = paymentSignedCallback('IPN-RETRY', 50000, '818181');

    $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($payload))
        ->assertOk()
        ->assertJson(['RspCode' => '99']);

    expect(DB::connection('bstore_payment')->table('payments')->find($paymentId)->status)->toBe('pending')
        ->and(DB::connection('bstore_payment')->table('payment_transactions')->where('transaction_code', '818181')->value('status'))->toBe('sync_pending');
});

test('IPN rejects invalid signature and mismatched amount without mutation', function () {
    $paymentId = paymentCreateVnpayPayment('IPN-VERIFY', 50000);
    $payload = paymentSignedCallback('IPN-VERIFY', 50000, '717171');
    $payload['vnp_Amount'] = '1';

    $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($payload))->assertJson(['RspCode' => '97']);
    expect(DB::connection('bstore_payment')->table('payments')->find($paymentId)->status)->toBe('pending');

    $payload = paymentSignedCallback('IPN-VERIFY', 51000, '717171');
    $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($payload))->assertJson(['RspCode' => '04']);
});

test('internal refund calls signed VNPAY refund once and updates payment', function () {
    $paymentId = paymentCreatePaidVnpayPayment('PAY-REFUND', 50000, '123456');
    Http::fake(function ($request) {
        $requestData = $request->data();
        $body = [
            'vnp_ResponseId' => 'RESP01',
            'vnp_Command' => 'refund',
            'vnp_ResponseCode' => '00',
            'vnp_Message' => 'Success',
            'vnp_TmnCode' => 'TESTTMN',
            'vnp_TxnRef' => $requestData['vnp_TxnRef'],
            'vnp_Amount' => $requestData['vnp_Amount'],
            'vnp_BankCode' => 'NCB',
            'vnp_PayDate' => '20260713110000',
            'vnp_TransactionNo' => '654321',
            'vnp_TransactionType' => $requestData['vnp_TransactionType'],
            'vnp_TransactionStatus' => '00',
            'vnp_OrderInfo' => $requestData['vnp_OrderInfo'],
        ];
        $body['vnp_SecureHash'] = paymentRefundResponseHash($body);

        return Http::response($body);
    });
    $headers = ['X-Internal-Service-Token' => 'internal-secret'];
    $data = ['amount' => 50000, 'reason' => 'Hoan don 123', 'requested_by' => 'admin:1', 'idempotency_key' => 'refund-request-10'];

    $this->withHeaders($headers)->postJson('/api/internal/payments/123/refunds', $data)
        ->assertOk()->assertJsonPath('data.status', 'refunded');
    $this->withHeaders($headers)->postJson('/api/internal/payments/123/refunds', $data)
        ->assertOk()->assertJsonPath('data.status', 'refunded');

    expect(DB::connection('bstore_payment')->table('payments')->find($paymentId)->status)->toBe('refunded')
        ->and(DB::connection('bstore_payment')->table('payment_refunds')->count())->toBe(1);
    Http::assertSentCount(1);
    Http::assertSent(function ($request) {
        $data = $request->data();
        $withoutHash = $data;
        unset($withoutHash['vnp_SecureHash']);

        return $request->url() === 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction'
            && $data['vnp_Command'] === 'refund'
            && $data['vnp_Amount'] === 5000000
            && $data['vnp_SecureHash'] === hash_hmac('sha512', implode('|', array_values($withoutHash)), 'test-secret');
    });
});

function paymentFakeOrderContext(int $orderId, int $customerId, int $amount, string $method): void
{
    Http::fake([
        "http://order.test/api/internal/orders/{$orderId}/payment-context*" => Http::response([
            'success' => true,
            'data' => [
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'final_amount' => $amount,
                'payment_method' => $method,
                'payment_status' => 'pending',
                'order_status' => 'pending',
            ],
        ]),
    ]);
}

function paymentAccessToken(int $userId, string $role = 'CUSTOMER'): string
{
    $now = Carbon::now()->timestamp;
    $header = paymentBase64Url(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
    $payload = paymentBase64Url(json_encode([
        'token_type' => 'access', 'sub' => $userId, 'role' => $role, 'sid' => 'session-1', 'jti' => 'jti-1',
        'iat' => $now - 1, 'nbf' => $now - 1, 'exp' => $now + 3600,
    ], JSON_THROW_ON_ERROR));
    $signature = paymentBase64Url(hash_hmac('sha256', "{$header}.{$payload}", 'payment-access-secret-at-least-32-bytes', true));

    return "{$header}.{$payload}.{$signature}";
}

function paymentBase64Url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function paymentCreateVnpayPayment(string $txnRef, int $amount, string $status = 'pending'): int
{
    return DB::connection('bstore_payment')->table('payments')->insertGetId([
        'order_id' => 123, 'payment_method' => 'vnpay', 'payment_provider' => 'vnpay',
        'transaction_code' => $txnRef, 'amount' => $amount, 'status' => $status,
        'paid_at' => $status === 'paid' ? now() : null,
    ]);
}

function paymentCreatePaidVnpayPayment(string $txnRef, int $amount, string $transactionNo): int
{
    $id = paymentCreateVnpayPayment($txnRef, $amount, 'paid');
    DB::connection('bstore_payment')->table('payment_transactions')->insert([
        ['payment_id' => $id, 'transaction_code' => $txnRef, 'provider' => 'vnpay', 'amount' => $amount, 'status' => 'pending', 'response_data' => json_encode(['event' => 'create_payment_url', 'vnp_CreateDate' => '20260713100000'])],
        ['payment_id' => $id, 'transaction_code' => $transactionNo, 'provider' => 'vnpay', 'amount' => $amount, 'status' => 'paid', 'response_data' => json_encode(['vnp_TransactionNo' => $transactionNo])],
    ]);

    return $id;
}

function paymentSignedCallback(string $txnRef, int $amount, string $transactionNo): array
{
    $payload = [
        'vnp_Amount' => (string) ($amount * 100), 'vnp_ResponseCode' => '00', 'vnp_TmnCode' => 'TESTTMN',
        'vnp_TransactionStatus' => '00', 'vnp_TxnRef' => $txnRef, 'vnp_TransactionNo' => $transactionNo,
    ];
    $payload['vnp_SecureHash'] = paymentVnpayHash($payload);

    return $payload;
}

function paymentVnpayHash(array $payload): string
{
    unset($payload['vnp_SecureHash'], $payload['vnp_SecureHashType']);
    ksort($payload);
    $data = collect($payload)->map(fn ($value, $key) => urlencode($key).'='.urlencode((string) $value))->implode('&');

    return hash_hmac('sha512', $data, 'test-secret');
}

function paymentRefundResponseHash(array $body): string
{
    $fields = [
        'vnp_ResponseId', 'vnp_Command', 'vnp_ResponseCode', 'vnp_Message', 'vnp_TmnCode', 'vnp_TxnRef',
        'vnp_Amount', 'vnp_BankCode', 'vnp_PayDate', 'vnp_TransactionNo', 'vnp_TransactionType',
        'vnp_TransactionStatus', 'vnp_OrderInfo',
    ];

    return hash_hmac('sha512', implode('|', array_map(fn ($field) => (string) ($body[$field] ?? ''), $fields)), 'test-secret');
}
