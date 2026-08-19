<?php

use App\Models\WarrantyRequest;
use App\Services\AuthTokenService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'database.connections.bstore_order' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ],
        'database.connections.bstore_catalog' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ],
    ]);
    DB::purge('bstore_order');
    DB::purge('bstore_catalog');

    Schema::connection('bstore_order')->create('orders', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->index();
        $table->string('order_code')->nullable();
        $table->string('receiver_name');
        $table->string('receiver_phone', 20);
        $table->string('receiver_email')->nullable();
        $table->text('shipping_address');
        $table->string('shipping_method');
        $table->decimal('total_amount', 15, 2)->default(0);
        $table->decimal('discount_amount', 15, 2)->default(0);
        $table->decimal('final_amount', 15, 2)->default(0);
        $table->string('status')->default('pending');
        $table->string('payment_status')->default('paid');
        $table->timestamp('delivered_at')->nullable();
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
        $table->decimal('price', 15, 2)->default(0);
        $table->unsignedInteger('quantity')->default(1);
        $table->decimal('subtotal', 15, 2)->default(0);
    });
    Schema::connection('bstore_order')->create('warranty_requests', function (Blueprint $table) {
        $table->id();
        $table->string('request_code')->nullable()->unique();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('order_id');
        $table->unsignedBigInteger('order_item_id');
        $table->unsignedBigInteger('product_id')->nullable();
        $table->string('type');
        $table->text('reason')->nullable();
        $table->text('description')->nullable();
        $table->string('image_url')->nullable();
        $table->string('status')->default('pending');
        $table->text('admin_note')->nullable();
        $table->text('rejection_reason')->nullable();
        $table->text('processing_note')->nullable();
        $table->unsignedBigInteger('approved_by')->nullable();
        $table->timestamp('approved_at')->nullable();
        $table->unsignedBigInteger('rejected_by')->nullable();
        $table->timestamp('rejected_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->date('warranty_start_date')->nullable();
        $table->date('warranty_end_date')->nullable();
        $table->timestamps();
    });

    Schema::connection('bstore_catalog')->create('warranty_policies', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('duration_months');
        $table->boolean('repair_support')->default(true);
        $table->string('status')->default('active');
    });
    Schema::connection('bstore_catalog')->create('products', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('warranty_policy_id')->nullable();
    });
    Schema::connection('bstore_catalog')->create('product_variants', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_id');
    });

    DB::connection('bstore_catalog')->table('warranty_policies')->insert([
        'id' => 1, 'name' => 'Bảo hành 12 thang', 'duration_months' => 12, 'repair_support' => true, 'status' => 'active',
    ]);
    DB::connection('bstore_catalog')->table('products')->insert(['id' => 100, 'warranty_policy_id' => 1]);
    DB::connection('bstore_catalog')->table('product_variants')->insert(['id' => 501, 'product_id' => 100]);
});

// Thực hiện bảo hành token.
function warrantyToken(int $id, string $role = 'CUSTOMER'): string
{
    return app(AuthTokenService::class)->generate($id, $role, "user{$id}@example.com");
}

// Thực hiện bảo hành đơn hàng.
function warrantyOrder(array $overrides = []): array
{
    $orderId = DB::connection('bstore_order')->table('orders')->insertGetId([
        'user_id' => $overrides['user_id'] ?? 10,
        'order_code' => $overrides['order_code'] ?? 'ORD-WARRANTY',
        'receiver_name' => 'Nguyen Van A',
        'receiver_phone' => '0900000001',
        'receiver_email' => 'a@example.com',
        'shipping_address' => '12 Nguyen Hue',
        'shipping_method' => 'standard',
        'status' => $overrides['status'] ?? 'delivered',
        'payment_status' => 'paid',
        'delivered_at' => $overrides['delivered_at'] ?? now()->subMonth(),
        'created_at' => now()->subMonths(2),
        'updated_at' => now()->subMonth(),
    ]);
    $itemId = DB::connection('bstore_order')->table('order_items')->insertGetId([
        'order_id' => $orderId,
        'product_id' => 100,
        'product_variant_id' => 501,
        'product_name' => 'Phone A',
        'price' => 100000,
        'quantity' => 1,
        'subtotal' => 100000,
    ]);

    return [$orderId, $itemId];
}

// Tạo hoặc lưu bảo hành through API.
function createWarrantyThroughApi($test, int $userId = 10, array $overrides = [])
{
    [$orderId, $itemId] = warrantyOrder(['user_id' => $userId] + $overrides);

    return $test->withToken(warrantyToken($userId))->postJson('/api/customer/warranty-requests', [
        'order_id' => $orderId,
        'order_item_id' => $itemId,
        'reason' => 'Sản phẩm không khởi động',
        'description' => 'Thiet bi không lên nguồn',
    ]);
}

test('customer submits a valid warranty request', function () {
    createWarrantyThroughApi($this)
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.product.id', 100);

    expect(WarrantyRequest::first()->request_code)->toMatch('/^WR-\d{8}-\d{6}$/');
});

test('customer submits a warranty request for a completed order', function () {
    createWarrantyThroughApi($this, 10, ['status' => 'completed'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');
});

test('customer cannot submit warranty for another customer order', function () {
    [$orderId, $itemId] = warrantyOrder(['user_id' => 11]);
    $this->withToken(warrantyToken(10))->postJson('/api/customer/warranty-requests', [
        'order_id' => $orderId, 'order_item_id' => $itemId, 'reason' => 'Không khởi động',
    ])->assertForbidden();
});

test('undelivered order is rejected', function () {
    createWarrantyThroughApi($this, 10, ['status' => 'shipping'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Đơn hàng chưa được giao thành công');
});

test('expired warranty is rejected', function () {
    createWarrantyThroughApi($this, 10, ['delivered_at' => now()->subMonths(13)])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Sản phẩm đã hết hạn bảo hành');
});

test('duplicate active warranty request returns conflict', function () {
    $response = createWarrantyThroughApi($this);
    $response->assertCreated();
    $warranty = WarrantyRequest::first();

    $this->withToken(warrantyToken(10))->postJson('/api/customer/warranty-requests', [
        'order_id' => $warranty->order_id,
        'order_item_id' => $warranty->order_item_id,
        'reason' => 'Van con loi',
    ])->assertConflict();
});

test('admin approves a pending request', function () {
    createWarrantyThroughApi($this)->assertCreated();
    $id = WarrantyRequest::first()->id;

    $this->withToken(warrantyToken(90, 'ADMIN'))->putJson("/api/admin/warranty-requests/{$id}/approve", [
        'processing_note' => 'Tiep nhan kiem tra',
    ])->assertOk()->assertJsonPath('data.status', 'approved');
});

test('staff rejects a pending request', function () {
    createWarrantyThroughApi($this)->assertCreated();
    $id = WarrantyRequest::first()->id;

    $this->withToken(warrantyToken(91, 'STAFF'))->putJson("/api/admin/warranty-requests/{$id}/reject", [
        'rejection_reason' => 'Loi không thuộc phạm vi bảo hành',
    ])->assertOk()->assertJsonPath('data.status', 'rejected');
});

test('rejection reason is required', function () {
    createWarrantyThroughApi($this)->assertCreated();
    $id = WarrantyRequest::first()->id;

    $this->withToken(warrantyToken(90, 'ADMIN'))
        ->putJson("/api/admin/warranty-requests/{$id}/reject", [])
        ->assertUnprocessable();
});

test('processed request cannot be approved again', function () {
    createWarrantyThroughApi($this)->assertCreated();
    $id = WarrantyRequest::first()->id;
    $token = warrantyToken(90, 'ADMIN');

    $this->withToken($token)->putJson("/api/admin/warranty-requests/{$id}/approve")->assertOk();
    $this->withToken($token)->putJson("/api/admin/warranty-requests/{$id}/approve")->assertConflict();
});

test('customer cannot view another customer warranty request', function () {
    createWarrantyThroughApi($this, 11)->assertCreated();
    $id = WarrantyRequest::first()->id;

    $this->withToken(warrantyToken(10))
        ->getJson("/api/customer/warranty-requests/{$id}")
        ->assertForbidden();
});

test('warranty follows the complete state machine', function () {
    createWarrantyThroughApi($this)->assertCreated();
    $id = WarrantyRequest::first()->id;
    $token = warrantyToken(90, 'ADMIN');

    $this->withToken($token)->putJson("/api/admin/warranty-requests/{$id}/approve")->assertOk();
    $this->withToken($token)->putJson("/api/admin/warranty-requests/{$id}/processing")->assertOk();
    $this->withToken($token)->putJson("/api/admin/warranty-requests/{$id}/complete", [
        'processing_note' => 'Da sua xong',
    ])->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.processing_note', 'Da sua xong');

    expect(WarrantyRequest::find($id)->completed_at)->not->toBeNull();
});
