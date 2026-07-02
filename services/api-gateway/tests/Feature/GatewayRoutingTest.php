<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'microservices.services.auth.url' => 'http://auth.test',
        'microservices.services.catalog.url' => 'http://catalog.test',
        'microservices.services.order.url' => 'http://order.test',
        'microservices.services.payment.url' => 'http://payment.test',
    ]);
});

test('admin customer routes are forwarded to auth service', function () {
    Http::fake([
        'http://auth.test/api/admin/customers' => Http::response([
            'success' => true,
            'data' => [],
        ]),
    ]);

    $this->withHeader('Authorization', 'Bearer test-token')
        ->getJson('/api/admin/customers')
        ->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'http://auth.test/api/admin/customers'
        && $request->hasHeader('authorization', 'Bearer test-token'));
});

test('existing admin catalog routes still go to catalog service', function () {
    Http::fake([
        'http://catalog.test/api/admin/brands' => Http::response([
            'success' => true,
            'data' => [],
        ]),
    ]);

    $this->getJson('/api/admin/brands')->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'http://catalog.test/api/admin/brands');
});

test('admin order routes are forwarded to order service', function () {
    Http::fake([
        'http://order.test/api/admin/orders' => Http::response([
            'success' => true,
            'data' => [],
        ]),
        'http://order.test/api/admin/orders/123' => Http::response([
            'success' => true,
            'data' => ['order_id' => 123],
        ]),
        'http://order.test/api/admin/orders/123/status' => Http::response([
            'success' => true,
            'data' => ['order_id' => 123, 'status' => 'confirmed'],
        ]),
    ]);

    $this->withHeader('Authorization', 'Bearer admin-token')
        ->getJson('/api/admin/orders')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer admin-token')
        ->getJson('/api/admin/orders/123')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer admin-token')
        ->patchJson('/api/admin/orders/123/status', ['status' => 'confirmed'])
        ->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/admin/orders'
        && $request->method() === 'GET'
        && $request->hasHeader('authorization', 'Bearer admin-token'));

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/admin/orders/123'
        && $request->method() === 'GET'
        && $request->hasHeader('authorization', 'Bearer admin-token'));

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/admin/orders/123/status'
        && $request->method() === 'PATCH'
        && $request->hasHeader('authorization', 'Bearer admin-token'));
});

test('cart detail routes are forwarded to order service', function () {
    Http::fake([
        'http://order.test/api/carts/10' => Http::response([
            'success' => true,
            'data' => ['id' => 10],
        ]),
    ]);

    $this->getJson('/api/carts/10')->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/carts/10');
});

test('profile routes are forwarded to auth service', function () {
    Http::fake([
        'http://auth.test/api/profile' => Http::response([
            'success' => true,
            'data' => ['id' => 1],
        ]),
    ]);

    $this->withHeader('Authorization', 'Bearer customer-token')
        ->getJson('/api/profile')
        ->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'http://auth.test/api/profile'
        && $request->hasHeader('authorization', 'Bearer customer-token'));
});

test('customer order routes are forwarded to order service', function () {
    Http::fake([
        'http://order.test/api/customer/orders' => Http::response([
            'success' => true,
            'data' => [],
        ]),
    ]);

    $this->withHeader('Authorization', 'Bearer customer-token')
        ->getJson('/api/customer/orders')
        ->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/customer/orders'
        && $request->hasHeader('authorization', 'Bearer customer-token'));
});

test('internal routes are forwarded to the owning service', function () {
    Http::fake([
        'http://order.test/api/internal/customers/10/orders' => Http::response([
            'success' => true,
            'data' => [],
        ]),
        'http://order.test/api/internal/orders/99/payment-status' => Http::response([
            'success' => true,
            'data' => ['order_id' => 99, 'payment_status' => 'paid'],
        ]),
        'http://order.test/api/internal/orders/99/cart/clear' => Http::response([
            'success' => true,
            'data' => ['order_id' => 99, 'deleted_items' => 2],
        ]),
        'http://payment.test/api/internal/orders/99/payment' => Http::response([
            'success' => true,
            'data' => ['status' => 'paid'],
        ]),
    ]);

    $this->getJson('/api/internal/customers/10/orders')->assertOk();
    $this->patchJson('/api/internal/orders/99/payment-status', [
        'payment_status' => 'paid',
        'status' => 'confirmed',
    ])->assertOk();
    $this->postJson('/api/internal/orders/99/cart/clear')->assertOk();
    $this->getJson('/api/internal/orders/99/payment')->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/internal/customers/10/orders');
    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/internal/orders/99/payment-status'
        && $request->method() === 'PATCH'
        && $request['payment_status'] === 'paid');
    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/internal/orders/99/cart/clear'
        && $request->method() === 'POST');
    Http::assertSent(fn ($request) => $request->url() === 'http://payment.test/api/internal/orders/99/payment');
});

test('VNPAY create route is forwarded to payment service with authorization header', function () {
    Http::fake([
        'http://payment.test/api/payments/vnpay/create' => Http::response([
            'success' => true,
            'data' => ['payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'],
        ], 201),
    ]);

    $this->withHeader('Authorization', 'Bearer customer-token')
        ->postJson('/api/payments/vnpay/create', [
            'order_id' => 123,
            'amount' => 90000,
            'order_info' => 'Thanh toan don hang 123',
        ])
        ->assertCreated();

    Http::assertSent(fn ($request) => $request->url() === 'http://payment.test/api/payments/vnpay/create'
        && $request->method() === 'POST'
        && $request->hasHeader('authorization', 'Bearer customer-token'));
});

test('VNPAY return route is forwarded to payment service with full query string and no auth requirement', function () {
    Http::fake([
        'http://payment.test/api/payments/vnpay/return*' => Http::response([
            'success' => true,
            'data' => ['verified' => true],
        ]),
    ]);

    $query = [
        'vnp_Amount' => '9000000',
        'vnp_BankCode' => 'NCB',
        'vnp_BankTranNo' => 'VNP14131242',
        'vnp_CardType' => 'ATM',
        'vnp_OrderInfo' => 'Thanh toan don hang 123',
        'vnp_PayDate' => '20260702104512',
        'vnp_ResponseCode' => '00',
        'vnp_TmnCode' => '3U5A2FCK',
        'vnp_TransactionNo' => '14131242',
        'vnp_TransactionStatus' => '00',
        'vnp_TxnRef' => '123',
        'vnp_SecureHash' => 'abc123',
    ];

    $this->getJson('/api/payments/vnpay/return?'.http_build_query($query, '', '&', PHP_QUERY_RFC1738))
        ->assertOk();

    Http::assertSent(function ($request) use ($query) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $forwardedQuery);

        return str_starts_with($request->url(), 'http://payment.test/api/payments/vnpay/return?')
            && $request->method() === 'GET'
            && $forwardedQuery === $query;
    });
});

test('VNPAY IPN route is forwarded to payment service with full query string', function () {
    Http::fake([
        'http://payment.test/api/payments/vnpay/ipn*' => Http::response([
            'RspCode' => '00',
            'Message' => 'Confirm Success',
        ]),
    ]);

    $query = [
        'vnp_Amount' => '9000000',
        'vnp_ResponseCode' => '00',
        'vnp_TmnCode' => '3U5A2FCK',
        'vnp_TransactionNo' => '14131242',
        'vnp_TransactionStatus' => '00',
        'vnp_TxnRef' => '123',
        'vnp_SecureHash' => 'abc123',
    ];

    $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($query, '', '&', PHP_QUERY_RFC1738))
        ->assertOk();

    Http::assertSent(function ($request) use ($query) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $forwardedQuery);

        return str_starts_with($request->url(), 'http://payment.test/api/payments/vnpay/ipn?')
            && $request->method() === 'GET'
            && $forwardedQuery === $query;
    });
});
