<?php

use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\ProductVariant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'database.default' => 'bstore_catalog',
        'database.connections.bstore_catalog' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    DB::purge('bstore_catalog');

    Schema::connection('bstore_catalog')->create('product_variants', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('product_id');
        $table->decimal('price', 15, 2)->default(0);
        $table->string('sku', 191)->unique();
        $table->string('status', 20)->default('active');
    });
    Schema::connection('bstore_catalog')->create('inventories', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('product_variant_id')->unique();
        $table->integer('quantity')->default(0);
        $table->integer('reserved_quantity')->default(0);
    });
    Schema::connection('bstore_catalog')->create('inventory_reservations', function (Blueprint $table): void {
        $table->id();
        $table->string('reference', 191);
        $table->unsignedBigInteger('product_variant_id');
        $table->unsignedInteger('quantity');
        $table->string('status', 20)->default('reserved');
        $table->timestamps();
        $table->unique(['reference', 'product_variant_id']);
    });
    Schema::connection('bstore_catalog')->create('inventory_transactions', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('product_variant_id');
        $table->string('type', 50);
        $table->integer('quantity');
        $table->string('reference', 191)->nullable();
        $table->text('note')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
    });

    ProductVariant::create([
        'product_id' => 1,
        'price' => 100,
        'sku' => 'PHONE-BLACK',
        'status' => 'active',
    ]);
    Inventory::create([
        'product_variant_id' => 1,
        'quantity' => 10,
        'reserved_quantity' => 0,
    ]);
});

test('reserve is idempotent and only increments reserved stock once', function () {
    $payload = reservationPayload('ORDER-101', 4);

    internalPost($this, '/api/internal/inventory/reservations', $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', 'reserved');

    internalPost($this, '/api/internal/inventory/reservations', $payload)
        ->assertOk()
        ->assertJsonPath('data.items.0.quantity', 4);

    expect(Inventory::first()->reserved_quantity)->toBe(4)
        ->and(InventoryReservation::count())->toBe(1)
        ->and(InventoryTransaction::where('type', 'reserve')->count())->toBe(1);
});

test('same reference with a different payload is rejected', function () {
    internalPost($this, '/api/internal/inventory/reservations', reservationPayload('ORDER-102', 2))
        ->assertCreated();

    internalPost($this, '/api/internal/inventory/reservations', reservationPayload('ORDER-102', 3))
        ->assertStatus(409)
        ->assertJsonPath('success', false);

    expect(Inventory::first()->reserved_quantity)->toBe(2);
});

test('insufficient inventory rolls back the whole reservation', function () {
    internalPost($this, '/api/internal/inventory/reservations', reservationPayload('ORDER-103', 11))
        ->assertStatus(409)
        ->assertJsonPath('data.available', 10);

    expect(Inventory::first()->reserved_quantity)->toBe(0)
        ->and(InventoryReservation::count())->toBe(0)
        ->and(InventoryTransaction::count())->toBe(0);
});

test('commit decrements physical and reserved stock exactly once', function () {
    internalPost($this, '/api/internal/inventory/reservations', reservationPayload('ORDER-104', 3))
        ->assertCreated();

    internalPost($this, '/api/internal/inventory/reservations/ORDER-104/commit')
        ->assertOk()
        ->assertJsonPath('data.status', 'committed');
    internalPost($this, '/api/internal/inventory/reservations/ORDER-104/commit')
        ->assertOk();

    $inventory = Inventory::first();

    expect($inventory->quantity)->toBe(7)
        ->and($inventory->reserved_quantity)->toBe(0)
        ->and(InventoryReservation::first()->status)->toBe('committed')
        ->and(InventoryTransaction::where('type', 'commit')->count())->toBe(1);
});

test('release returns reserved capacity without changing physical stock', function () {
    internalPost($this, '/api/internal/inventory/reservations', reservationPayload('ORDER-105', 5))
        ->assertCreated();
    internalPost($this, '/api/internal/inventory/reservations/ORDER-105/release')
        ->assertOk();
    internalPost($this, '/api/internal/inventory/reservations/ORDER-105/release')
        ->assertOk();

    $inventory = Inventory::first();

    expect($inventory->quantity)->toBe(10)
        ->and($inventory->reserved_quantity)->toBe(0)
        ->and(InventoryReservation::first()->status)->toBe('released')
        ->and(InventoryTransaction::where('type', 'release')->count())->toBe(1);
});

test('restore puts committed stock back exactly once', function () {
    internalPost($this, '/api/internal/inventory/reservations', reservationPayload('ORDER-106', 2))
        ->assertCreated();
    internalPost($this, '/api/internal/inventory/reservations/ORDER-106/commit')->assertOk();
    internalPost($this, '/api/internal/inventory/reservations/ORDER-106/restore')
        ->assertOk()
        ->assertJsonPath('data.status', 'restored');
    internalPost($this, '/api/internal/inventory/reservations/ORDER-106/restore')->assertOk();

    expect(Inventory::first()->quantity)->toBe(10)
        ->and(Inventory::first()->reserved_quantity)->toBe(0)
        ->and(InventoryTransaction::where('type', 'restore')->count())->toBe(1);
});

test('serialized reservations cannot oversell the same inventory row', function () {
    internalPost($this, '/api/internal/inventory/reservations', reservationPayload('ORDER-107', 6))
        ->assertCreated();
    internalPost($this, '/api/internal/inventory/reservations', reservationPayload('ORDER-108', 6))
        ->assertStatus(409)
        ->assertJsonPath('data.available', 4);

    expect(Inventory::first()->reserved_quantity)->toBe(6)
        ->and(InventoryReservation::count())->toBe(1);
});

test('invalid lifecycle transitions are rejected', function () {
    internalPost($this, '/api/internal/inventory/reservations', reservationPayload('ORDER-109', 1))
        ->assertCreated();
    internalPost($this, '/api/internal/inventory/reservations/ORDER-109/commit')->assertOk();

    internalPost($this, '/api/internal/inventory/reservations/ORDER-109/release')
        ->assertStatus(409)
        ->assertJsonPath('data.status', 'committed');
});

test('inactive variants cannot be reserved', function () {
    ProductVariant::query()->whereKey(1)->update(['status' => 'inactive']);

    internalPost($this, '/api/internal/inventory/reservations', reservationPayload('ORDER-110', 1))
        ->assertStatus(409)
        ->assertJsonPath('data.product_variant_id', 1);

    expect(Inventory::first()->reserved_quantity)->toBe(0);
});

test('admin inventory mutation rejects negative and server-owned reserved quantities', function () {
    $this->patchJson('/api/inventories/1', ['quantity' => -1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    internalPost($this, '/api/internal/inventory/reservations', reservationPayload('ORDER-111', 4))
        ->assertCreated();

    $this->patchJson('/api/inventories/1', [
        'quantity' => 3,
        'reserved_quantity' => 4,
    ])->assertUnprocessable();

    $this->patchJson('/api/inventories/1', [
        'quantity' => 10,
        'reserved_quantity' => 0,
    ])->assertUnprocessable();

    expect(Inventory::first()->quantity)->toBe(10)
        ->and(Inventory::first()->reserved_quantity)->toBe(4);
});

// Thực hiện reservation dữ liệu gửi.
function reservationPayload(string $reference, int $quantity): array
{
    return [
        'reference' => $reference,
        'items' => [[
            'product_variant_id' => 1,
            'quantity' => $quantity,
        ]],
    ];
}

// Thực hiện nội bộ post.
function internalPost($test, string $uri, array $payload = [])
{
    return $test
        ->withHeader('X-Internal-Service-Token', 'catalog-service-test-internal-token')
        ->postJson($uri, $payload);
}
