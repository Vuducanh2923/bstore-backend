<?php

use App\Services\AuthTokenService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'database.default' => 'bstore_order',
        'database.connections.bstore_order' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
        'database.connections.bstore_auth' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
        'services.auth.url' => 'http://auth.test',
    ]);

    DB::purge('bstore_order');
    DB::purge('bstore_auth');
    Mail::fake();

    foreach (['notifications', 'order_histories', 'complaints', 'refund_requests', 'order_items', 'orders'] as $table) {
        Schema::connection('bstore_order')->dropIfExists($table);
    }

    foreach (['users', 'roles'] as $table) {
        Schema::connection('bstore_auth')->dropIfExists($table);
    }

    Schema::connection('bstore_order')->create('orders', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->index();
        $table->string('order_code', 191)->nullable()->unique();
        $table->string('receiver_name');
        $table->string('receiver_phone', 20);
        $table->string('receiver_email', 191)->nullable();
        $table->text('shipping_address');
        $table->string('shipping_method', 50);
        $table->string('payment_method', 50)->nullable();
        $table->decimal('total_amount', 15, 2)->default(0);
        $table->decimal('discount_amount', 15, 2)->default(0);
        $table->decimal('shipping_fee', 15, 2)->default(0);
        $table->decimal('final_amount', 15, 2)->default(0);
        $table->string('status', 20)->nullable()->default('pending');
        $table->string('cancel_request_status', 20)->default('none');
        $table->string('refund_status', 20)->default('none');
        $table->string('return_status', 20)->default('none');
        $table->string('payment_status', 20)->nullable()->default('unpaid');
        $table->timestamp('paid_at')->nullable();
        $table->unsignedBigInteger('assigned_staff_id')->nullable()->index();
        $table->string('assigned_staff_name', 191)->nullable();
        $table->timestamp('assigned_at')->nullable();
        $table->text('processing_note')->nullable();
        $table->text('cancel_reason')->nullable();
        $table->text('return_reason')->nullable();
        $table->text('note')->nullable();
        $table->dateTime('created_at')->nullable();
        $table->dateTime('updated_at')->nullable();
    });

    Schema::connection('bstore_order')->create('order_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id')->index();
        $table->unsignedBigInteger('product_variant_id')->index();
        $table->string('product_name');
        $table->decimal('price', 15, 2)->default(0);
        $table->unsignedInteger('quantity')->default(1);
        $table->decimal('subtotal', 15, 2)->default(0);
    });

    Schema::connection('bstore_order')->create('refund_requests', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id')->index();
        $table->unsignedBigInteger('customer_id')->index();
        $table->text('reason');
        $table->decimal('amount', 15, 2)->default(0);
        $table->string('status', 20)->default('pending')->index();
        $table->unsignedBigInteger('approved_by')->nullable()->index();
        $table->timestamp('approved_at')->nullable();
        $table->string('refund_method', 50)->nullable();
        $table->string('refund_transaction', 191)->nullable();
        $table->text('admin_note')->nullable();
        $table->timestamps();
    });

    Schema::connection('bstore_order')->create('complaints', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id')->index();
        $table->unsignedBigInteger('customer_id')->index();
        $table->unsignedBigInteger('staff_id')->nullable()->index();
        $table->string('staff_name', 191)->nullable();
        $table->string('staff_phone', 30)->nullable();
        $table->string('title', 191);
        $table->text('content');
        $table->string('status', 20)->default('pending')->index();
        $table->text('reply')->nullable();
        $table->timestamp('handled_at')->nullable();
        $table->timestamps();
    });

    Schema::connection('bstore_order')->create('order_histories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id')->index();
        $table->string('action', 50)->index();
        $table->string('old_status', 20)->nullable();
        $table->string('new_status', 20)->nullable();
        $table->unsignedBigInteger('staff_id')->nullable()->index();
        $table->string('staff_name', 191)->nullable();
        $table->text('note')->nullable();
        $table->timestamp('created_at')->nullable();
    });

    Schema::connection('bstore_order')->create('notifications', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable()->index();
        $table->unsignedBigInteger('order_id')->nullable()->index();
        $table->string('type', 50)->index();
        $table->string('title', 191)->nullable();
        $table->text('message');
        $table->json('data')->nullable();
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });

    Schema::connection('bstore_auth')->create('roles', function (Blueprint $table) {
        $table->id();
        $table->string('name', 100)->unique();
    });

    Schema::connection('bstore_auth')->create('users', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('role_id')->nullable();
        $table->string('full_name', 191);
        $table->string('email', 191)->unique();
        $table->string('phone', 30)->nullable();
    });

    DB::connection('bstore_auth')->table('roles')->insert([
        ['id' => 1, 'name' => 'ADMIN'],
        ['id' => 2, 'name' => 'STAFF'],
        ['id' => 3, 'name' => 'CUSTOMER'],
    ]);

    DB::connection('bstore_auth')->table('users')->insert([
        ['id' => 1, 'role_id' => 1, 'full_name' => 'Admin One', 'email' => 'admin@example.com', 'phone' => '0900000000'],
        ['id' => 2, 'role_id' => 2, 'full_name' => 'Staff Two', 'email' => 'staff2@example.com', 'phone' => '0900000002'],
        ['id' => 3, 'role_id' => 2, 'full_name' => 'Staff Three', 'email' => 'staff3@example.com', 'phone' => '0900000003'],
        ['id' => 10, 'role_id' => 3, 'full_name' => 'Customer Ten', 'email' => 'customer@example.com', 'phone' => '0900000010'],
    ]);

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/api/internal/payments/')) {
            return Http::response([
                'success' => true,
                'data' => ['status' => config('testing.refund_provider_status', 'refunded')],
            ]);
        }

        $userId = (int) basename($request->url());
        $user = DB::connection('bstore_auth')->table('users')->find($userId);

        return $user
            ? Http::response([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
            ])
            : Http::response(['success' => false], 404);
    });
});

// Thực hiện đơn hàng quy trình token.
function orderWorkflowToken(int $id, string $role, string $email): string
{
    return app(AuthTokenService::class)->generate($id, $role, $email);
}

// Thực hiện insert quy trình đơn hàng.
function insertWorkflowOrder(array $overrides = []): int
{
    return DB::connection('bstore_order')->table('orders')->insertGetId([
        'user_id' => $overrides['user_id'] ?? 10,
        'order_code' => $overrides['order_code'] ?? ('ORD-WF-'.uniqid()),
        'receiver_name' => $overrides['receiver_name'] ?? 'Customer Ten',
        'receiver_phone' => $overrides['receiver_phone'] ?? '0900000010',
        'receiver_email' => $overrides['receiver_email'] ?? 'customer@example.com',
        'shipping_address' => $overrides['shipping_address'] ?? '12 Nguyen Hue',
        'shipping_method' => $overrides['shipping_method'] ?? 'standard',
        'payment_method' => $overrides['payment_method'] ?? 'cod',
        'total_amount' => $overrides['total_amount'] ?? 100000,
        'discount_amount' => $overrides['discount_amount'] ?? 0,
        'shipping_fee' => $overrides['shipping_fee'] ?? 0,
        'final_amount' => $overrides['final_amount'] ?? 100000,
        'status' => $overrides['status'] ?? 'pending',
        'cancel_request_status' => $overrides['cancel_request_status'] ?? 'none',
        'refund_status' => $overrides['refund_status'] ?? 'none',
        'return_status' => $overrides['return_status'] ?? 'none',
        'payment_status' => $overrides['payment_status'] ?? 'unpaid',
        'assigned_staff_id' => $overrides['assigned_staff_id'] ?? null,
        'assigned_staff_name' => $overrides['assigned_staff_name'] ?? null,
        'assigned_at' => $overrides['assigned_at'] ?? null,
        'created_at' => $overrides['created_at'] ?? now(),
        'updated_at' => $overrides['updated_at'] ?? now(),
    ]);
}

test('staff accepts an order and only the assigned staff can move status sequentially', function () {
    $orderId = insertWorkflowOrder();
    $staffToken = orderWorkflowToken(2, 'STAFF', 'staff2@example.com');
    $otherStaffToken = orderWorkflowToken(3, 'STAFF', 'staff3@example.com');

    $this->withToken($staffToken)
        ->putJson("/api/admin/orders/{$orderId}/assign", [
            'processing_note' => 'Nhận xử lý ca sang',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.assigned_staff_id', 2)
        ->assertJsonPath('data.assigned_staff_name', 'Staff Two');

    $this->withToken($otherStaffToken)
        ->patchJson("/api/admin/orders/{$orderId}/status", [
            'status' => 'shipping',
        ])
        ->assertForbidden();

    $this->withToken($staffToken)
        ->patchJson("/api/admin/orders/{$orderId}/status", [
            'status' => 'shipping',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'shipping');

    $this->assertDatabaseHas('order_histories', [
        'order_id' => $orderId,
        'action' => 'order_assigned',
        'staff_id' => 2,
    ], 'bstore_order');
});

test('order status cannot skip steps or change after delivered', function () {
    $pendingOrderId = insertWorkflowOrder();
    $deliveredOrderId = insertWorkflowOrder([
        'status' => 'delivered',
        'assigned_staff_id' => 2,
        'assigned_staff_name' => 'Staff Two',
        'assigned_at' => now(),
    ]);
    $adminToken = orderWorkflowToken(1, 'ADMIN', 'admin@example.com');

    $this->withToken($adminToken)
        ->patchJson("/api/admin/orders/{$pendingOrderId}/status", [
            'status' => 'shipping',
        ])
        ->assertUnprocessable();

    $this->withToken($adminToken)
        ->patchJson("/api/admin/orders/{$deliveredOrderId}/status", [
            'status' => 'shipping',
        ])
        ->assertUnprocessable();
});

test('customer cancel request can be approved and creates refund for paid online order', function () {
    $orderId = insertWorkflowOrder([
        'status' => 'processing',
        'payment_status' => 'paid',
        'payment_method' => 'vnpay',
        'assigned_staff_id' => 2,
        'assigned_staff_name' => 'Staff Two',
        'assigned_at' => now(),
    ]);

    $this->withToken(orderWorkflowToken(10, 'CUSTOMER', 'customer@example.com'))
        ->postJson("/api/customer/orders/{$orderId}/cancel", [
            'reason' => 'Khach doi y',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.cancel_request_status', 'pending');

    $this->withToken(orderWorkflowToken(2, 'STAFF', 'staff2@example.com'))
        ->putJson("/api/admin/orders/{$orderId}/cancel/approve", [
            'admin_note' => 'Dong y huy',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancel_request_status', 'approved')
        ->assertJsonPath('data.refund_status', 'pending');

    $this->assertDatabaseHas('refund_requests', [
        'order_id' => $orderId,
        'customer_id' => 10,
        'status' => 'pending',
    ], 'bstore_order');
});

test('unpaid order is cancelled immediately but shipping order cannot be cancelled', function () {
    $pendingOrderId = insertWorkflowOrder(['status' => 'pending', 'payment_status' => 'unpaid']);
    $shippingOrderId = insertWorkflowOrder(['status' => 'shipping', 'payment_status' => 'unpaid']);
    $customer = orderWorkflowToken(10, 'CUSTOMER', 'customer@example.com');

    $this->withToken($customer)->postJson("/api/customer/orders/{$pendingOrderId}/cancel", [
        'reason' => 'Không con nhu cau',
    ])->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancel_request_status', 'approved')
        ->assertJsonPath('data.refund_status', 'none');

    $this->withToken($customer)->postJson("/api/customer/orders/{$shippingOrderId}/cancel", [
        'reason' => 'Không con nhu cau',
    ])->assertUnprocessable();
});

test('delivered order can complete normally and return follows its own workflow', function () {
    $normalOrderId = insertWorkflowOrder([
        'status' => 'delivered',
        'assigned_staff_id' => 2,
        'assigned_staff_name' => 'Staff Two',
    ]);
    $returnOrderId = insertWorkflowOrder([
        'status' => 'delivered',
        'assigned_staff_id' => 2,
        'assigned_staff_name' => 'Staff Two',
    ]);
    $staff = orderWorkflowToken(2, 'STAFF', 'staff2@example.com');

    $this->withToken($staff)->patchJson("/api/admin/orders/{$normalOrderId}/status", [
        'status' => 'completed',
    ])->assertOk()->assertJsonPath('data.status', 'completed');

    $this->withToken(orderWorkflowToken(10, 'CUSTOMER', 'customer@example.com'))
        ->postJson("/api/customer/orders/{$returnOrderId}/return", ['reason' => 'Sản phẩm bị lỗi'])
        ->assertOk()
        ->assertJsonPath('data.status', 'delivered')
        ->assertJsonPath('data.return_status', 'pending');

    foreach (['approved', 'received', 'completed'] as $returnStatus) {
        $this->withToken($staff)
            ->putJson("/api/admin/orders/{$returnOrderId}/return/{$returnStatus}")
            ->assertOk()
            ->assertJsonPath('data.return_status', $returnStatus);
    }

    $this->assertDatabaseHas('orders', [
        'id' => $returnOrderId,
        'status' => 'completed',
        'return_status' => 'completed',
    ], 'bstore_order');
});

test('refund flow is restricted to assigned staff or admin', function () {
    $orderId = insertWorkflowOrder([
        'status' => 'delivered',
        'payment_status' => 'paid',
        'payment_method' => 'vnpay',
        'assigned_staff_id' => 2,
        'assigned_staff_name' => 'Staff Two',
        'assigned_at' => now(),
    ]);

    $refundId = $this->withToken(orderWorkflowToken(10, 'CUSTOMER', 'customer@example.com'))
        ->postJson('/api/refunds', [
            'order_id' => $orderId,
            'reason' => 'Sản phẩm loi',
            'amount' => 100000,
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken(orderWorkflowToken(3, 'STAFF', 'staff3@example.com'))
        ->putJson("/api/refunds/{$refundId}/approve", [
            'admin_note' => 'Duyet',
        ])
        ->assertForbidden();

    $this->withToken(orderWorkflowToken(2, 'STAFF', 'staff2@example.com'))
        ->putJson("/api/refunds/{$refundId}/approve", [
            'admin_note' => 'Duyet',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $this->withToken(orderWorkflowToken(2, 'STAFF', 'staff2@example.com'))
        ->putJson("/api/refunds/{$refundId}/refunding", [
            'admin_note' => 'Gửi VNPay',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'status' => 'delivered',
        'refund_status' => 'completed',
        'payment_status' => 'refunded',
    ], 'bstore_order');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/internal/payments/')
        && $request->hasHeader('X-Internal-Service-Token', 'test-internal-token')
        && $request['idempotency_key'] === "refund-{$refundId}"
        && $request['requested_by'] === 'user2'
    );
});

test('customer refund validates delivered paid amount and rejects duplicates', function () {
    $processingOrderId = insertWorkflowOrder([
        'status' => 'processing',
        'payment_status' => 'paid',
        'payment_method' => 'vnpay',
    ]);
    $customer = orderWorkflowToken(10, 'CUSTOMER', 'customer@example.com');

    $this->withToken($customer)->postJson('/api/refunds', [
        'order_id' => $processingOrderId,
        'reason' => 'Sản phẩm loi',
    ])->assertUnprocessable();

    $deliveredOrderId = insertWorkflowOrder([
        'status' => 'delivered',
        'payment_status' => 'paid',
        'payment_method' => 'vnpay',
        'final_amount' => 100000,
    ]);
    $this->withToken($customer)->postJson('/api/refunds', [
        'order_id' => $deliveredOrderId,
        'reason' => 'Sản phẩm loi',
        'amount' => 100001,
    ])->assertUnprocessable();

    $this->withToken($customer)->postJson('/api/refunds', [
        'order_id' => $deliveredOrderId,
        'reason' => 'Sản phẩm loi',
        'amount' => 100000,
    ])->assertCreated();
    $this->withToken($customer)->postJson('/api/refunds', [
        'order_id' => $deliveredOrderId,
        'reason' => 'Gửi trung',
        'amount' => 100000,
    ])->assertUnprocessable();
});

test('online refund remains refunding while provider processes and retries idempotently', function () {
    config(['testing.refund_provider_status' => 'processing']);
    $orderId = insertWorkflowOrder([
        'status' => 'delivered',
        'payment_status' => 'paid',
        'payment_method' => 'vnpay',
        'assigned_staff_id' => 2,
        'assigned_staff_name' => 'Staff Two',
        'assigned_at' => now(),
    ]);
    $refundId = $this->withToken(orderWorkflowToken(10, 'CUSTOMER', 'customer@example.com'))
        ->postJson('/api/refunds', [
            'order_id' => $orderId,
            'reason' => 'Sản phẩm loi',
        ])->assertCreated()->json('data.id');
    $staff = orderWorkflowToken(2, 'STAFF', 'staff2@example.com');
    $this->withToken($staff)->putJson("/api/refunds/{$refundId}/approve")->assertOk();

    $this->withToken($staff)->putJson("/api/refunds/{$refundId}/refunding")
        ->assertOk()->assertJsonPath('data.status', 'refunding');
    $this->withToken($staff)->putJson("/api/refunds/{$refundId}/refunding")
        ->assertOk()->assertJsonPath('data.status', 'refunding');

    $requests = Http::recorded()
        ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/api/internal/payments/'))
        ->values();
    expect($requests)->toHaveCount(2)
        ->and($requests[0][0]['idempotency_key'])->toBe("refund-{$refundId}")
        ->and($requests[1][0]['idempotency_key'])->toBe("refund-{$refundId}");
    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'status' => 'delivered',
        'payment_status' => 'paid',
    ], 'bstore_order');
});

test('complaint stores assigned staff contact and only that staff can resolve it', function () {
    $orderId = insertWorkflowOrder([
        'status' => 'processing',
        'assigned_staff_id' => 2,
        'assigned_staff_name' => 'Staff Two',
        'assigned_at' => now(),
    ]);

    $complaintId = $this->withToken(orderWorkflowToken(10, 'CUSTOMER', 'customer@example.com'))
        ->postJson('/api/complaints', [
            'order_id' => $orderId,
            'title' => 'Giao hang cham',
            'content' => 'Đơn hàng giao chậm hơn hẹn.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.assigned_staff_name', 'Staff Two')
        ->assertJsonPath('data.assigned_staff_phone', '0900000002')
        ->json('data.id');

    $this->withToken(orderWorkflowToken(3, 'STAFF', 'staff3@example.com'))
        ->putJson("/api/complaints/{$complaintId}/resolve", [
            'reply' => 'Da xử lý',
        ])
        ->assertForbidden();

    $this->withToken(orderWorkflowToken(2, 'STAFF', 'staff2@example.com'))
        ->putJson("/api/complaints/{$complaintId}/process")
        ->assertOk()
        ->assertJsonPath('data.status', 'processing');

    $this->withToken(orderWorkflowToken(2, 'STAFF', 'staff2@example.com'))
        ->putJson("/api/complaints/{$complaintId}/resolve", [
            'reply' => 'Đã liên hệ khách hàng',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.reply', 'Đã liên hệ khách hàng');
});
