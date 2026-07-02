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
        'services.vnpay' => [
            'tmn_code' => 'TESTTMN',
            'hash_secret' => 'test-secret',
            'payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'return_url' => 'http://localhost:5173/payment/vnpay-return',
            'ipn_url' => 'http://localhost:8000/api/payments/vnpay/ipn',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ],
        'services.order.url' => 'http://order.test',
        'services.timeout' => 10,
    ]);

    DB::purge('bstore_payment');

    Schema::connection('bstore_payment')->create('payments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id')->index();
        $table->string('payment_method', 50);
        $table->string('payment_provider', 50)->nullable();
        $table->string('transaction_code', 191)->nullable();
        $table->decimal('amount', 15, 2)->default(0);
        $table->string('status', 20)->nullable()->default('pending');
        $table->dateTime('paid_at')->nullable();
    });

    Schema::connection('bstore_payment')->create('payment_transactions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('payment_id')->index();
        $table->string('transaction_code', 191)->unique();
        $table->string('provider', 100);
        $table->decimal('amount', 15, 2)->default(0);
        $table->string('status', 20);
        $table->json('response_data')->nullable();
    });

    Schema::connection('bstore_payment')->create('invoices', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('payment_id')->index();
        $table->unsignedBigInteger('order_id')->index();
        $table->string('invoice_code', 191)->unique();
        $table->decimal('total_amount', 15, 2)->default(0);
        $table->dateTime('issued_at')->nullable();
    });
});

afterEach(function () {
    Carbon::setTestNow();
});

test('creates a signed VNPAY payment URL', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-02 10:30:40', 'Asia/Ho_Chi_Minh'));
    Http::fake();

    $response = $this
        ->withHeader('X-Forwarded-For', '1.2.3.4, 5.6.7.8')
        ->withHeader('Authorization', 'Bearer customer-token')
        ->postJson('/api/payments/vnpay/create', [
            'order_id' => 123,
            'amount' => 90000,
            'order_info' => 'Thanh toan don hang 123',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.order_id', 123)
        ->assertJsonPath('data.amount', '90000.00');

    $paymentUrl = $response->json('data.payment_url');
    parse_str((string) parse_url($paymentUrl, PHP_URL_QUERY), $query);

    expect((string) parse_url($paymentUrl, PHP_URL_SCHEME).'://'.parse_url($paymentUrl, PHP_URL_HOST).parse_url($paymentUrl, PHP_URL_PATH))
        ->toBe('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html')
        ->and($query['vnp_Version'])->toBe('2.1.0')
        ->and($query['vnp_Command'])->toBe('pay')
        ->and($query['vnp_TmnCode'])->toBe('TESTTMN')
        ->and($query['vnp_Amount'])->toBe('9000000')
        ->and($query['vnp_CurrCode'])->toBe('VND')
        ->and($query['vnp_TxnRef'])->toBe((string) $response->json('data.payment_id'))
        ->and($query['vnp_OrderInfo'])->toBe('Thanh toan don hang 123')
        ->and($query['vnp_OrderType'])->toBe('other')
        ->and($query['vnp_Locale'])->toBe('vn')
        ->and($query['vnp_ReturnUrl'])->toBe('http://localhost:5173/payment/vnpay-return')
        ->and($query['vnp_IpAddr'])->toBe('1.2.3.4')
        ->and($query['vnp_CreateDate'])->toBe('20260702103040');

    expect($query['vnp_SecureHash'])->toBe(vnpayPaymentTestHash($query));

    $payment = DB::connection('bstore_payment')->table('payments')->first();

    expect($payment->status)->toBe('pending')
        ->and($payment->payment_method)->toBe('vnpay')
        ->and($payment->payment_provider)->toBe('vnpay')
        ->and($payment->transaction_code)->toBe((string) $payment->id);

    Http::assertNothingSent();
});

test('creates a signed VNPAY payment URL with default order info', function () {
    Http::fake();

    $response = $this->postJson('/api/payments/vnpay/create', [
        'order_id' => 456,
        'amount' => 1000,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.order_id', 456);

    parse_str((string) parse_url($response->json('data.payment_url'), PHP_URL_QUERY), $query);

    expect($query['vnp_OrderInfo'])->toBe('Thanh toan don hang 456');
    Http::assertNothingSent();
});

test('create VNPAY URL returns clear validation errors for missing fields', function () {
    $this->postJson('/api/payments/vnpay/create', [])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Payload tao thanh toan VNPAY khong hop le')
        ->assertJsonValidationErrors(['order_id', 'amount'])
        ->assertJsonMissingValidationErrors(['order_info'])
        ->assertJsonPath('data.required_fields.amount', 'required numeric integer min:1000');

    expect(DB::connection('bstore_payment')->table('payments')->count())->toBe(0);
});

test('create VNPAY URL rejects amount below minimum', function () {
    $this->postJson('/api/payments/vnpay/create', [
        'order_id' => 123,
        'amount' => 999,
        'order_info' => 'Thanh toan don hang 123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount'])
        ->assertJsonPath('errors.amount.0', 'amount phai lon hon hoac bang 1000');

    expect(DB::connection('bstore_payment')->table('payments')->count())->toBe(0);
});

test('create VNPAY URL rejects fractional VND amount', function () {
    $this->postJson('/api/payments/vnpay/create', [
        'order_id' => 123,
        'amount' => 90000.5,
        'order_info' => 'Thanh toan don hang 123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount'])
        ->assertJsonPath('errors.amount.0', 'amount phai la so nguyen VND');

    expect(DB::connection('bstore_payment')->table('payments')->count())->toBe(0);
});

test('create VNPAY URL does not require order lookup in order service', function () {
    Http::fake();

    $this->postJson('/api/payments/vnpay/create', [
        'order_id' => 999,
        'amount' => 90000,
        'order_info' => 'Thanh toan don hang 999',
    ])
        ->assertCreated()
        ->assertJsonPath('data.order_id', 999);

    expect(DB::connection('bstore_payment')->table('payments')->where('order_id', 999)->exists())->toBeTrue();
    Http::assertNothingSent();
});

test('create VNPAY URL failure returns clear error and marks order payment failed', function () {
    config(['services.vnpay.hash_secret' => '']);

    Http::fake([
        'http://order.test/api/orders/123' => Http::response([
            'success' => true,
            'data' => ['id' => 123, 'payment_status' => 'failed'],
        ]),
    ]);

    $response = $this
        ->withHeader('Authorization', 'Bearer customer-token')
        ->postJson('/api/payments/vnpay/create', [
            'order_id' => 123,
            'amount' => 90000,
            'order_info' => 'Thanh toan don hang 123',
        ]);

    $response
        ->assertStatus(500)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Khong tao duoc URL thanh toan VNPAY')
        ->assertJsonPath('payload.order_id', 123)
        ->assertJsonPath('data.order_update.updated', true);

    expect(DB::connection('bstore_payment')->table('payments')->count())->toBe(0);

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/orders/123'
        && $request->method() === 'PATCH'
        && $request['payment_status'] === 'failed'
        && (
            $request->hasHeader('Authorization', 'Bearer customer-token')
            || $request->hasHeader('authorization', 'Bearer customer-token')
        ));
});

test('creates another payment URL for the same unpaid order without creating another order', function () {
    Http::fake();

    $this->postJson('/api/payments/vnpay/create', [
        'order_id' => 123,
        'amount' => 90000,
        'order_info' => 'Thanh toan lai don hang 123',
    ])->assertCreated();

    $this->postJson('/api/payments/vnpay/create', [
        'order_id' => 123,
        'amount' => 90000,
        'order_info' => 'Thanh toan lai don hang 123',
    ])
        ->assertCreated()
        ->assertJsonPath('data.order_id', 123);

    expect(DB::connection('bstore_payment')->table('payments')->where('order_id', 123)->count())->toBe(2);
    Http::assertNothingSent();
});

test('return callback verifies signature and marks payment paid', function () {
    Http::fake([
        'http://order.test/api/internal/orders/123/payment-status' => Http::response([
            'success' => true,
            'data' => [
                'order_id' => 123,
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ],
        ]),
        'http://order.test/api/internal/orders/123/cart/clear' => Http::response([
            'success' => true,
            'data' => [
                'order_id' => 123,
                'user_id' => 10,
                'deleted_items' => 2,
            ],
        ]),
    ]);

    $txnRef = 'PAY-RETURN-123';
    $paymentId = vnpayPaymentTestCreatePayment($txnRef, 120000);
    $payload = vnpayPaymentTestSignedPayload([
        'vnp_TxnRef' => $txnRef,
        'vnp_Amount' => '12000000',
        'vnp_ResponseCode' => '00',
        'vnp_TmnCode' => 'TESTTMN',
        'vnp_TransactionStatus' => '00',
        'vnp_TransactionNo' => '14131242',
        'vnp_OrderInfo' => 'Thanh toan don hang 123',
    ]);

    $this->getJson('/api/payments/vnpay/return?'.http_build_query($payload, '', '&', PHP_QUERY_RFC1738))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Thanh toán thành công')
        ->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.successful', true)
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payment_id', $paymentId)
        ->assertJsonPath('data.order_id', 123)
        ->assertJsonPath('data.amount', '120000.00')
        ->assertJsonPath('data.order_updated', true)
        ->assertJsonPath('data.transaction_code', $txnRef)
        ->assertJsonPath('data.cart_clear.cleared', true)
        ->assertJsonPath('data.cart_clear.response.data.deleted_items', 2);

    $payment = DB::connection('bstore_payment')->table('payments')->where('id', $paymentId)->first();
    $transaction = DB::connection('bstore_payment')->table('payment_transactions')->where('payment_id', $paymentId)->latest('id')->first();

    expect($payment->status)->toBe('paid')
        ->and($payment->paid_at)->not->toBeNull()
        ->and($transaction->transaction_code)->toBe('14131242')
        ->and($transaction->status)->toBe('paid');

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/internal/orders/123/cart/clear'
        && $request->method() === 'POST'
        && $request['reason'] === 'vnpay_paid');
    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/internal/orders/123/payment-status'
        && $request->method() === 'PATCH'
        && $request['payment_status'] === 'paid'
        && $request['status'] === 'confirmed'
        && $request['payment_method'] === 'vnpay');
});

test('return callback stays successful when order service payment update fails', function () {
    Http::fake([
        'http://order.test/api/internal/orders/123/payment-status' => Http::response([
            'success' => false,
            'message' => 'Order update failed',
        ], 500),
        'http://order.test/api/internal/orders/123/cart/clear' => Http::response([
            'success' => true,
            'data' => ['order_id' => 123, 'deleted_items' => 2],
        ]),
    ]);

    $txnRef = 'PAY-RETURN-ORDER-UPDATE-FAILS';
    $paymentId = vnpayPaymentTestCreatePayment($txnRef, 120000);
    $payload = vnpayPaymentTestSignedPayload([
        'vnp_TxnRef' => $txnRef,
        'vnp_Amount' => '12000000',
        'vnp_ResponseCode' => '00',
        'vnp_TmnCode' => 'TESTTMN',
        'vnp_TransactionStatus' => '00',
        'vnp_TransactionNo' => '14131243',
        'vnp_OrderInfo' => 'Thanh toan don hang 123',
    ]);

    $this->getJson('/api/payments/vnpay/return?'.http_build_query($payload, '', '&', PHP_QUERY_RFC1738))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Thanh toán thành công')
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payment_id', $paymentId)
        ->assertJsonPath('data.order_id', 123)
        ->assertJsonPath('data.order_updated', false)
        ->assertJsonPath('data.order_update.status', 500);

    $payment = DB::connection('bstore_payment')->table('payments')->where('id', $paymentId)->first();

    expect($payment->status)->toBe('paid')
        ->and($payment->paid_at)->not->toBeNull();
});

test('return callback is idempotent for the same VNPAY transaction number', function () {
    Http::fake([
        'http://order.test/api/internal/orders/123/payment-status' => Http::response([
            'success' => true,
            'data' => [
                'order_id' => 123,
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ],
        ]),
        'http://order.test/api/internal/orders/123/cart/clear' => Http::sequence()
            ->push([
                'success' => true,
                'data' => ['order_id' => 123, 'deleted_items' => 2],
            ])
            ->push([
                'success' => true,
                'data' => ['order_id' => 123, 'deleted_items' => 0],
            ]),
    ]);

    $txnRef = 'PAY-RETURN-IDEMPOTENT';
    $paymentId = vnpayPaymentTestCreatePayment($txnRef, 120000);
    $payload = vnpayPaymentTestSignedPayload([
        'vnp_TxnRef' => $txnRef,
        'vnp_Amount' => '12000000',
        'vnp_ResponseCode' => '00',
        'vnp_TmnCode' => 'TESTTMN',
        'vnp_TransactionStatus' => '00',
        'vnp_TransactionNo' => '14131242',
        'vnp_OrderInfo' => 'Thanh toan don hang 123',
    ]);
    $query = http_build_query($payload, '', '&', PHP_QUERY_RFC1738);

    $this->getJson('/api/payments/vnpay/return?'.$query)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Thanh toán thành công')
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payment_id', $paymentId);

    $this->getJson('/api/payments/vnpay/return?'.$query)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Thanh toán thành công')
        ->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.successful', true)
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payment_id', $paymentId)
        ->assertJsonPath('data.order_id', 123)
        ->assertJsonPath('data.order_updated', true)
        ->assertJsonPath('data.cart_clear.response.data.deleted_items', 0);

    $payment = DB::connection('bstore_payment')->table('payments')->where('id', $paymentId)->first();
    $transactions = DB::connection('bstore_payment')->table('payment_transactions')
        ->where('transaction_code', '14131242')
        ->where('provider', 'vnpay')
        ->count();

    expect($payment->status)->toBe('paid')
        ->and($transactions)->toBe(1);

    Http::assertSentCount(4);
});

test('return callback keeps an already paid payment successful when a later callback reports failure', function () {
    Http::fake([
        'http://order.test/api/internal/orders/123/payment-status' => Http::response([
            'success' => true,
            'data' => [
                'order_id' => 123,
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ],
        ]),
        'http://order.test/api/internal/orders/123/cart/clear' => Http::response([
            'success' => true,
            'data' => ['order_id' => 123, 'deleted_items' => 0],
        ]),
    ]);

    $txnRef = 'PAY-RETURN-ALREADY-PAID';
    $paymentId = vnpayPaymentTestCreatePayment($txnRef, 120000, 'paid');
    $payload = vnpayPaymentTestSignedPayload([
        'vnp_TxnRef' => $txnRef,
        'vnp_Amount' => '12000000',
        'vnp_ResponseCode' => '24',
        'vnp_TmnCode' => 'TESTTMN',
        'vnp_TransactionStatus' => '02',
        'vnp_TransactionNo' => '14139999',
        'vnp_OrderInfo' => 'Khach reload callback cu',
    ]);

    $this->getJson('/api/payments/vnpay/return?'.http_build_query($payload, '', '&', PHP_QUERY_RFC1738))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Thanh toán thành công')
        ->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.successful', true)
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payment_id', $paymentId)
        ->assertJsonPath('data.order_id', 123)
        ->assertJsonPath('data.amount', '120000.00')
        ->assertJsonPath('data.order_updated', true);

    $payment = DB::connection('bstore_payment')->table('payments')->where('id', $paymentId)->first();
    $transactions = DB::connection('bstore_payment')->table('payment_transactions')
        ->where('transaction_code', '14139999')
        ->count();

    expect($payment->status)->toBe('paid')
        ->and($payment->paid_at)->not->toBeNull()
        ->and($transactions)->toBe(0);
});

test('return callback keeps all VNPAY params when verifying signature', function () {
    Http::fake([
        'http://order.test/api/internal/orders/123/payment-status' => Http::response([
            'success' => true,
            'data' => [
                'order_id' => 123,
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ],
        ]),
        'http://order.test/api/internal/orders/123/cart/clear' => Http::response([
            'success' => true,
            'data' => [
                'order_id' => 123,
                'deleted_items' => 1,
            ],
        ]),
    ]);

    $txnRef = 'PAY-RETURN-FULL';
    $paymentId = vnpayPaymentTestCreatePayment($txnRef, 120000);
    $payload = vnpayPaymentTestSignedPayload([
        'vnp_Amount' => '12000000',
        'vnp_BankCode' => 'NCB',
        'vnp_BankTranNo' => 'VNP14131242',
        'vnp_CardType' => 'ATM',
        'vnp_OrderInfo' => 'Thanh toan don hang 123',
        'vnp_PayDate' => '20260702104512',
        'vnp_ResponseCode' => '00',
        'vnp_TmnCode' => 'TESTTMN',
        'vnp_TransactionNo' => '14131242',
        'vnp_TransactionStatus' => '00',
        'vnp_TxnRef' => $txnRef,
    ]);

    $this->getJson('/api/payments/vnpay/return?'.http_build_query($payload, '', '&', PHP_QUERY_RFC1738))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Thanh toán thành công')
        ->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.successful', true)
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payment_id', $paymentId)
        ->assertJsonPath('data.order_id', 123)
        ->assertJsonPath('data.amount', '120000.00')
        ->assertJsonPath('data.order_updated', true)
        ->assertJsonPath('data.transaction_code', $txnRef)
        ->assertJsonPath('data.cart_clear.cleared', true);

    $payment = DB::connection('bstore_payment')->table('payments')->where('id', $paymentId)->first();

    expect($payment->status)->toBe('paid');
});

test('return callback rejects response code 00 when secure hash is invalid', function () {
    $txnRef = 'PAY-RETURN-BAD-HASH';
    $paymentId = vnpayPaymentTestCreatePayment($txnRef, 120000);
    $payload = vnpayPaymentTestSignedPayload([
        'vnp_Amount' => '12000000',
        'vnp_BankCode' => 'NCB',
        'vnp_OrderInfo' => 'Thanh toan don hang 123',
        'vnp_ResponseCode' => '00',
        'vnp_TmnCode' => 'TESTTMN',
        'vnp_TransactionNo' => '14131242',
        'vnp_TransactionStatus' => '00',
        'vnp_TxnRef' => $txnRef,
    ]);
    $payload['vnp_BankCode'] = 'VCB';

    $this->getJson('/api/payments/vnpay/return?'.http_build_query($payload, '', '&', PHP_QUERY_RFC1738))
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertExactJson([
            'success' => false,
            'message' => 'Chu ky VNPAY khong hop le',
        ]);

    $payment = DB::connection('bstore_payment')->table('payments')->where('id', $paymentId)->first();
    $transactions = DB::connection('bstore_payment')->table('payment_transactions')->where('payment_id', $paymentId)->count();

    expect($payment->status)->toBe('pending')
        ->and($payment->paid_at)->toBeNull()
        ->and($transactions)->toBe(0);
});

test('return callback returns success when secure hash is invalid but payment was already paid', function () {
    Http::fake([
        'http://order.test/api/internal/orders/123/payment-status' => Http::response([
            'success' => true,
            'data' => [
                'order_id' => 123,
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ],
        ]),
    ]);

    $txnRef = 'PAY-RETURN-BAD-HASH-PAID';
    $paymentId = vnpayPaymentTestCreatePayment($txnRef, 120000, 'paid');
    $payload = vnpayPaymentTestSignedPayload([
        'vnp_Amount' => '12000000',
        'vnp_BankCode' => 'NCB',
        'vnp_OrderInfo' => 'Thanh toan don hang 123',
        'vnp_ResponseCode' => '00',
        'vnp_TmnCode' => 'TESTTMN',
        'vnp_TransactionNo' => '14137777',
        'vnp_TransactionStatus' => '00',
        'vnp_TxnRef' => $txnRef,
    ]);
    $payload['vnp_BankCode'] = 'VCB';

    $this->getJson('/api/payments/vnpay/return?'.http_build_query($payload, '', '&', PHP_QUERY_RFC1738))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Thanh toán thành công')
        ->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.successful', true)
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payment_id', $paymentId)
        ->assertJsonPath('data.order_id', 123)
        ->assertJsonPath('data.amount', '120000.00')
        ->assertJsonPath('data.order_updated', true);

    $transactions = DB::connection('bstore_payment')->table('payment_transactions')
        ->where('transaction_code', '14137777')
        ->count();

    expect($transactions)->toBe(0);
    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/internal/orders/123/payment-status'
        && $request->method() === 'PATCH'
        && $request['payment_status'] === 'paid');
});

test('return callback returns clear JSON when query params are missing', function () {
    $this->getJson('/api/payments/vnpay/return?vnp_ResponseCode=00')
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Thieu tham so VNPAY return')
        ->assertJsonPath('errors.missing.0', 'vnp_Amount')
        ->assertJsonPath('errors.missing.1', 'vnp_TmnCode')
        ->assertJsonPath('errors.missing.2', 'vnp_TransactionStatus')
        ->assertJsonPath('errors.missing.3', 'vnp_TxnRef')
        ->assertJsonPath('errors.missing.4', 'vnp_SecureHash');
});

test('ipn callback marks payment failed when VNPAY reports a failed transaction', function () {
    $txnRef = 'PAY-IPN-FAILED';
    $paymentId = vnpayPaymentTestCreatePayment($txnRef, 50000);
    $payload = vnpayPaymentTestSignedPayload([
        'vnp_TxnRef' => $txnRef,
        'vnp_Amount' => '5000000',
        'vnp_ResponseCode' => '24',
        'vnp_TransactionStatus' => '02',
        'vnp_TransactionNo' => '0',
        'vnp_OrderInfo' => 'Khach huy thanh toan',
    ]);

    $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($payload, '', '&', PHP_QUERY_RFC1738))
        ->assertOk()
        ->assertJson([
            'RspCode' => '00',
            'Message' => 'Confirm Success',
        ]);

    $payment = DB::connection('bstore_payment')->table('payments')->where('id', $paymentId)->first();

    expect($payment->status)->toBe('failed')
        ->and($payment->paid_at)->toBeNull();
});

test('ipn successful callback clears order cart', function () {
    Http::fake([
        'http://order.test/api/internal/orders/123/payment-status' => Http::response([
            'success' => true,
            'data' => [
                'order_id' => 123,
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ],
        ]),
        'http://order.test/api/internal/orders/123/cart/clear' => Http::response([
            'success' => true,
            'data' => [
                'order_id' => 123,
                'deleted_items' => 2,
            ],
        ]),
    ]);

    $txnRef = 'PAY-IPN-PAID';
    $paymentId = vnpayPaymentTestCreatePayment($txnRef, 50000);
    $payload = vnpayPaymentTestSignedPayload([
        'vnp_TxnRef' => $txnRef,
        'vnp_Amount' => '5000000',
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_TransactionNo' => '909090',
        'vnp_OrderInfo' => 'Thanh toan don hang 123',
    ]);

    $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($payload, '', '&', PHP_QUERY_RFC1738))
        ->assertOk()
        ->assertJson([
            'RspCode' => '00',
            'Message' => 'Confirm Success',
        ]);

    $payment = DB::connection('bstore_payment')->table('payments')->where('id', $paymentId)->first();

    expect($payment->status)->toBe('paid')
        ->and($payment->paid_at)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/internal/orders/123/cart/clear'
        && $request->method() === 'POST'
        && $request['reason'] === 'vnpay_paid');
    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/internal/orders/123/payment-status'
        && $request->method() === 'PATCH'
        && $request['payment_status'] === 'paid');
});

test('ipn callback rejects invalid secure hash without updating payment', function () {
    $txnRef = 'PAY-IPN-BAD-HASH';
    $paymentId = vnpayPaymentTestCreatePayment($txnRef, 70000);
    $payload = vnpayPaymentTestSignedPayload([
        'vnp_TxnRef' => $txnRef,
        'vnp_Amount' => '7000000',
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_TransactionNo' => '998877',
    ]);
    $payload['vnp_Amount'] = '7100000';

    $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($payload, '', '&', PHP_QUERY_RFC1738))
        ->assertOk()
        ->assertJson([
            'RspCode' => '97',
            'Message' => 'Invalid signature',
        ]);

    $payment = DB::connection('bstore_payment')->table('payments')->where('id', $paymentId)->first();
    $transactions = DB::connection('bstore_payment')->table('payment_transactions')->where('payment_id', $paymentId)->count();

    expect($payment->status)->toBe('pending')
        ->and($transactions)->toBe(0);
});

function vnpayPaymentTestCreatePayment(string $txnRef, int $amount, string $status = 'pending'): int
{
    return DB::connection('bstore_payment')->table('payments')->insertGetId([
        'order_id' => 123,
        'payment_method' => 'vnpay',
        'payment_provider' => 'vnpay',
        'transaction_code' => $txnRef,
        'amount' => $amount,
        'status' => $status,
        'paid_at' => $status === 'paid' ? now() : null,
    ]);
}

function vnpayPaymentTestSignedPayload(array $payload): array
{
    $payload['vnp_SecureHash'] = vnpayPaymentTestHash($payload);

    return $payload;
}

function vnpayPaymentTestHash(array $payload): string
{
    unset($payload['vnp_SecureHash'], $payload['vnp_SecureHashType']);
    ksort($payload);

    return hash_hmac('sha512', http_build_query($payload, '', '&', PHP_QUERY_RFC1738), 'test-secret');
}
