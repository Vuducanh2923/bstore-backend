<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'microservices.services.auth.url' => 'http://auth.test',
        'microservices.services.catalog.url' => 'http://catalog.test',
        'microservices.services.order.url' => 'http://order.test',
        'microservices.services.payment.url' => 'http://payment.test',
        'microservices.internal_token' => 'test-internal-token',
    ]);
});

test('admin customer routes are forwarded to auth service', function () {
    Http::fake([
        'http://auth.test/api/internal/auth/introspect' => Http::response(['data' => ['active' => true]]),
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
        'http://auth.test/api/internal/auth/introspect' => Http::response(['data' => ['active' => true]]),
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
        'http://order.test/api/admin/orders/123/assign' => Http::response([
            'success' => true,
            'data' => ['order_id' => 123, 'status' => 'processing'],
        ]),
        'http://order.test/api/admin/orders/123/cancel/approve' => Http::response([
            'success' => true,
            'data' => ['order_id' => 123, 'status' => 'cancelled'],
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

    $this->withHeader('Authorization', 'Bearer admin-token')
        ->putJson('/api/admin/orders/123/assign')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer admin-token')
        ->putJson('/api/admin/orders/123/cancel/approve')
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

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/admin/orders/123/assign'
        && $request->method() === 'PUT'
        && $request->hasHeader('authorization', 'Bearer admin-token'));

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/admin/orders/123/cancel/approve'
        && $request->method() === 'PUT'
        && $request->hasHeader('authorization', 'Bearer admin-token'));
});

test('customer and admin warranty routes are forwarded to order service', function () {
    Http::fake([
        'http://auth.test/api/internal/auth/introspect' => Http::response(['data' => ['active' => true]]),
        'http://order.test/api/customer/warranty-requests' => Http::response(['success' => true, 'data' => []]),
        'http://order.test/api/admin/warranty-requests/5/approve' => Http::response([
            'success' => true, 'data' => ['id' => 5, 'status' => 'approved'],
        ]),
    ]);

    $this->withHeader('Authorization', 'Bearer customer-token')
        ->getJson('/api/customer/warranty-requests')
        ->assertOk();
    $this->withHeader('Authorization', 'Bearer admin-token')
        ->putJson('/api/admin/warranty-requests/5/approve')
        ->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/customer/warranty-requests');
    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/admin/warranty-requests/5/approve');
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
        'http://auth.test/api/internal/auth/introspect' => Http::response(['data' => ['active' => true]]),
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
        'http://auth.test/api/internal/auth/introspect' => Http::response(['data' => ['active' => true]]),
        'http://order.test/api/customer/orders' => Http::response([
            'success' => true,
            'data' => [],
        ]),
        'http://order.test/api/customer/orders/123/cancel' => Http::response([
            'success' => true,
            'data' => ['status' => 'pending_cancel'],
        ]),
    ]);

    $this->withHeader('Authorization', 'Bearer customer-token')
        ->getJson('/api/customer/orders')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer customer-token')
        ->postJson('/api/customer/orders/123/cancel', ['reason' => 'doi y'])
        ->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/customer/orders'
        && $request->hasHeader('authorization', 'Bearer customer-token'));

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/customer/orders/123/cancel'
        && $request->method() === 'POST'
        && $request->hasHeader('authorization', 'Bearer customer-token'));
});

test('refund and complaint routes are forwarded to order service', function () {
    Http::fake([
        'http://auth.test/api/internal/auth/introspect' => Http::response(['data' => ['active' => true]]),
        'http://order.test/api/refunds' => Http::response([
            'success' => true,
            'data' => [],
        ]),
        'http://order.test/api/refunds/5/approve' => Http::response([
            'success' => true,
            'data' => ['id' => 5, 'status' => 'approved'],
        ]),
        'http://order.test/api/complaints' => Http::response([
            'success' => true,
            'data' => [],
        ]),
        'http://order.test/api/complaints/7/resolve' => Http::response([
            'success' => true,
            'data' => ['id' => 7, 'status' => 'resolved'],
        ]),
    ]);

    $this->withHeader('Authorization', 'Bearer token')
        ->getJson('/api/refunds')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer token')
        ->putJson('/api/refunds/5/approve')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer token')
        ->getJson('/api/complaints')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer token')
        ->putJson('/api/complaints/7/resolve')
        ->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/refunds'
        && $request->method() === 'GET');
    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/refunds/5/approve'
        && $request->method() === 'PUT');
    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/complaints'
        && $request->method() === 'GET');
    Http::assertSent(fn ($request) => $request->url() === 'http://order.test/api/complaints/7/resolve'
        && $request->method() === 'PUT');
});

test('internal routes are never exposed by the gateway', function () {
    Http::fake();

    $this->getJson('/api/internal/customers/10/orders')->assertNotFound();
    $this->patchJson('/api/internal/orders/99/payment-status', ['payment_status' => 'paid'])->assertNotFound();
    $this->postJson('/api/internal/orders/99/cart/clear')->assertNotFound();
    $this->getJson('/api/internal/orders/99/payment')->assertNotFound();

    Http::assertNothingSent();
});

test('VNPAY create route is forwarded to payment service with authorization header', function () {
    Http::fake([
        'http://auth.test/api/internal/auth/introspect' => Http::response(['data' => ['active' => true]]),
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

test('revoked access tokens are rejected before forwarding', function () {
    Http::fake([
        'http://auth.test/api/internal/auth/introspect' => Http::response(['data' => ['active' => false]]),
    ]);

    $this->withHeader('Authorization', 'Bearer revoked-token')
        ->getJson('/api/profile')
        ->assertUnauthorized();

    Http::assertNotSent(fn ($request) => $request->url() === 'http://auth.test/api/profile');
});

test('public catalog routes ignore an invalid authorization header', function (string $path) {
    Http::fake([
        'http://catalog.test/api/*' => Http::response(['success' => true, 'data' => []]),
    ]);

    $this->withHeader('Authorization', 'Bearer expired-or-invalid-token')
        ->getJson('/api/'.$path)
        ->assertOk();

    Http::assertNotSent(fn ($request) => $request->url() === 'http://auth.test/api/internal/auth/introspect');
})->with([
    'products' => 'products?page=1&per_page=12',
    'product sale' => 'products/sale',
    'categories' => 'categories',
    'brands' => 'brands',
    'banners' => 'banners',
    'home banners' => 'home/banners',
]);

test('external callers cannot inject the internal service token header', function () {
    Http::fake([
        'http://catalog.test/api/products' => Http::response(['success' => true, 'data' => []]),
    ]);

    $this->withHeader('X-Internal-Service-Token', 'attacker-token')
        ->getJson('/api/products')
        ->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'http://catalog.test/api/products'
        && ! $request->hasHeader('x-internal-service-token'));
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
