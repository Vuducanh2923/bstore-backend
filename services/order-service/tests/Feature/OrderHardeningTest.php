<?php

use App\Services\AuthTokenService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
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
        'database.connections.bstore_catalog' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
        'services.auth.url' => null,
        'order.shipping.flat_fee' => 30000,
        'order.shipping.free_threshold' => 20000000,
    ]);

    DB::purge('bstore_order');
    DB::purge('bstore_catalog');
    Mail::fake();

    Schema::connection('bstore_order')->create('orders', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->index();
        $table->string('order_code')->unique();
        $table->string('receiver_name');
        $table->string('receiver_phone');
        $table->string('receiver_email')->nullable();
        $table->text('shipping_address');
        $table->string('shipping_method');
        $table->string('payment_method')->nullable();
        $table->decimal('total_amount', 15, 2)->default(0);
        $table->decimal('discount_amount', 15, 2)->default(0);
        $table->decimal('shipping_fee', 15, 2)->default(0);
        $table->decimal('final_amount', 15, 2)->default(0);
        $table->string('status', 30)->default('pending');
        $table->string('cancel_request_status', 20)->default('none');
        $table->string('refund_status', 20)->default('none');
        $table->string('return_status', 20)->default('none');
        $table->string('payment_status', 20)->default('unpaid');
        $table->timestamp('paid_at')->nullable();
        $table->string('inventory_reference')->nullable()->unique();
        $table->string('inventory_state')->nullable();
        $table->timestamp('inventory_updated_at')->nullable();
        $table->unsignedBigInteger('assigned_staff_id')->nullable();
        $table->string('assigned_staff_name')->nullable();
        $table->timestamp('assigned_at')->nullable();
        $table->text('processing_note')->nullable();
        $table->text('cancel_reason')->nullable();
        $table->text('return_reason')->nullable();
        $table->text('note')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    Schema::connection('bstore_order')->create('order_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->unsignedBigInteger('product_id')->nullable();
        $table->unsignedBigInteger('product_variant_id');
        $table->string('product_name');
        $table->string('product_image')->nullable();
        $table->string('color')->nullable();
        $table->string('ram')->nullable();
        $table->string('storage')->nullable();
        $table->decimal('price', 15, 2);
        $table->unsignedInteger('quantity');
        $table->decimal('subtotal', 15, 2);
    });

    Schema::connection('bstore_order')->create('order_discounts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->unsignedBigInteger('discount_id');
        $table->string('discount_code');
        $table->decimal('discount_amount', 15, 2)->default(0);
    });

    Schema::connection('bstore_catalog')->create('products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->decimal('price', 15, 2);
        $table->decimal('sale_price', 15, 2)->nullable();
        $table->boolean('is_sale')->default(false);
        $table->string('status')->default('active');
    });

    Schema::connection('bstore_catalog')->create('product_variants', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_id');
        $table->string('color')->nullable();
        $table->string('ram')->nullable();
        $table->string('storage')->nullable();
        $table->decimal('price', 15, 2);
        $table->string('status')->default('active');
    });

    DB::connection('bstore_catalog')->table('products')->insert([
        'id' => 1,
        'name' => 'Server Phone',
        'price' => 1000000,
        'sale_price' => 900000,
        'is_sale' => true,
        'status' => 'active',
    ]);
    DB::connection('bstore_catalog')->table('product_variants')->insert([
        'id' => 11,
        'product_id' => 1,
        'color' => 'Black',
        'ram' => '8GB',
        'storage' => '256GB',
        'price' => 1000000,
        'status' => 'active',
    ]);

    Http::fake(function (ClientRequest $request) {
        $segments = explode('/', parse_url($request->url(), PHP_URL_PATH));
        $action = end($segments);
        $reference = $request->data()['reference'] ?? ($segments[count($segments) - 2] ?? null);

        return Http::response([
            'success' => true,
            'data' => [
                'reference' => $reference,
                'status' => $action === 'reservations' ? 'reserved' : $action.'ted',
                'items' => [],
            ],
        ], $action === 'reservations' ? 201 : 200);
    });
});

test('generic and unprotected order APIs are denied by default', function () {
    $this->getJson('/api/orders')->assertStatus(405);
    $this->postJson('/api/orders', [])->assertUnauthorized();
    $this->getJson('/api/discounts')->assertNotFound();
    $this->postJson('/api/order-items', [])->assertNotFound();
    $this->getJson('/api/internal/customers/10/orders')->assertUnauthorized();
    $this->withHeader('X-Internal-Service-Token', 'wrong-token')
        ->getJson('/api/internal/customers/10/orders')
        ->assertUnauthorized();
});

test('order creation ignores client identity prices totals and statuses', function () {
    $response = $this->withToken(customerAccessToken(10))->postJson('/api/orders', [
        'user_id' => 999,
        'receiver_name' => 'Customer Ten',
        'receiver_phone' => '0901234567',
        'receiver_email' => 'customer@example.com',
        'shipping_address' => '1 Nguyen Hue',
        'shipping_method' => 'standard',
        'payment_method' => 'cod',
        'total_amount' => 1,
        'discount_amount' => 999999999,
        'shipping_fee' => 0,
        'final_amount' => 1,
        'status' => 'delivered',
        'payment_status' => 'paid',
        'items' => [[
            'product_variant_id' => 11,
            'product_name' => 'Attacker Name',
            'price' => 1,
            'quantity' => 2,
            'subtotal' => 2,
        ]],
    ])->assertCreated();

    $orderId = (int) $response->json('data.id');
    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'user_id' => 10,
        'total_amount' => 1800000,
        'discount_amount' => 0,
        'shipping_fee' => 30000,
        'final_amount' => 1830000,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'inventory_state' => 'reserved',
    ], 'bstore_order');
    $this->assertDatabaseHas('order_items', [
        'order_id' => $orderId,
        'product_name' => 'Server Phone',
        'price' => 900000,
        'quantity' => 2,
        'subtotal' => 1800000,
    ], 'bstore_order');

    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/api/internal/inventory/reservations')
        && $request['items'][0] === ['product_variant_id' => 11, 'quantity' => 2]
        && $request->hasHeader('X-Internal-Service-Token', 'test-internal-token')
    );
});

test('inactive catalog variant is rejected before inventory reservation', function () {
    DB::connection('bstore_catalog')->table('product_variants')->where('id', 11)->update(['status' => 'inactive']);

    $this->withToken(customerAccessToken(10))->postJson('/api/orders', validOrderPayload())
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    expect(DB::connection('bstore_order')->table('orders')->count())->toBe(0);
    Http::assertNothingSent();
});

test('duplicate product variants are consolidated before order validation', function () {
    $payload = validOrderPayload();
    $payload['items'] = [
        ['product_variant_id' => 11, 'quantity' => 1],
        ['product_variant_id' => 11, 'quantity' => 2],
    ];

    $orderId = (int) $this->withToken(customerAccessToken(10))
        ->postJson('/api/orders', $payload)
        ->assertCreated()
        ->json('data.id');

    $this->assertDatabaseCount('order_items', 1, 'bstore_order');
    $this->assertDatabaseHas('order_items', [
        'order_id' => $orderId,
        'product_variant_id' => 11,
        'quantity' => 3,
    ], 'bstore_order');
});

test('inventory is reserved committed and restored across cod lifecycle', function () {
    $orderId = (int) $this->withToken(customerAccessToken(10))
        ->postJson('/api/orders', validOrderPayload())
        ->assertCreated()
        ->json('data.id');

    $admin = app(AuthTokenService::class)->generate(1, 'ADMIN', 'admin@example.com');
    $this->withToken($admin)->putJson("/api/admin/orders/{$orderId}/assign")
        ->assertOk()
        ->assertJsonPath('data.status', 'processing');
    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'inventory_state' => 'committed',
    ], 'bstore_order');

    $this->withToken(customerAccessToken(10))->postJson("/api/customer/orders/{$orderId}/cancel", [
        'reason' => 'Khong con nhu cau',
    ])->assertOk();
    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'status' => 'cancelled',
        'inventory_state' => 'restored',
    ], 'bstore_order');

    Http::assertSentCount(3);
    foreach (['/commit', '/restore'] as $suffix) {
        Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), $suffix));
    }
});

test('cancelling a pending order releases its reservation', function () {
    $orderId = (int) $this->withToken(customerAccessToken(10))
        ->postJson('/api/orders', validOrderPayload())
        ->assertCreated()
        ->json('data.id');
    $this->withToken(customerAccessToken(10))->postJson("/api/customer/orders/{$orderId}/cancel", [
        'reason' => 'Dat nham',
    ])->assertOk();

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'inventory_state' => 'released',
    ], 'bstore_order');
    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/release'));
});

test('online payment commits inventory but leaves order pending for staff assignment', function () {
    $payload = validOrderPayload();
    $payload['payment_method'] = 'vnpay';
    $orderId = (int) $this->withToken(customerAccessToken(10))
        ->postJson('/api/orders', $payload)
        ->assertCreated()
        ->json('data.id');

    $this->withHeaders(internalServiceHeaders())
        ->patchJson("/api/internal/orders/{$orderId}/payment-status", ['payment_status' => 'paid'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.payment_status', 'paid');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'status' => 'pending',
        'inventory_state' => 'committed',
    ], 'bstore_order');
});

function validOrderPayload(): array
{
    return [
        'receiver_name' => 'Customer Ten',
        'receiver_phone' => '0901234567',
        'receiver_email' => 'customer@example.com',
        'shipping_address' => '1 Nguyen Hue',
        'shipping_method' => 'standard',
        'payment_method' => 'cod',
        'items' => [[
            'product_variant_id' => 11,
            'quantity' => 1,
        ]],
    ];
}
