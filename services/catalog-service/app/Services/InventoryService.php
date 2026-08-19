<?php

namespace App\Services;

use App\Exceptions\InventoryReservationException;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InventoryService
{

    // Thực hiện reserve.
    public function reserve(string $reference, array $items): array
    {
        $items = $this->normalizeItems($items);

        return DB::connection('bstore_catalog')->transaction(function () use ($reference, $items): array {
            $existing = $this->reservationRows($reference);

            if ($existing->isNotEmpty()) {
                $this->assertSameItems($existing, $items);

                return [
                    'created' => false,
                    'data' => $this->reservationPayload($reference, $existing),
                ];
            }

            $variantIds = array_keys($items);
            $variants = ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($variantIds as $variantId) {
                $variant = $variants->get($variantId);

                if (! $variant) {
                    throw new InventoryReservationException(
                        'Biến thể sản phẩm không tồn tại',
                        409,
                        ['product_variant_id' => $variantId],
                    );
                }

                if (strtolower((string) $variant->status) !== 'active') {
                    throw new InventoryReservationException(
                        'Biến thể sản phẩm không hoạt động',
                        409,
                        ['product_variant_id' => $variantId],
                    );
                }

                Inventory::query()->firstOrCreate(
                    ['product_variant_id' => $variantId],
                    ['quantity' => 0, 'reserved_quantity' => 0],
                );
            }

            $inventories = $this->lockedInventories($variantIds);

            foreach ($items as $variantId => $requestedQuantity) {
                $inventory = $inventories->get($variantId);
                $available = (int) $inventory->quantity - (int) $inventory->reserved_quantity;

                if ($available < $requestedQuantity) {
                    throw new InventoryReservationException(
                        'Số lượng tồn kho không đủ',
                        409,
                        [
                            'product_variant_id' => $variantId,
                            'requested' => $requestedQuantity,
                            'available' => max(0, $available),
                        ],
                    );
                }
            }

            foreach ($items as $variantId => $requestedQuantity) {
                $inventory = $inventories->get($variantId);
                $inventory->reserved_quantity = (int) $inventory->reserved_quantity + $requestedQuantity;
                $inventory->save();

                InventoryReservation::create([
                    'reference' => $reference,
                    'product_variant_id' => $variantId,
                    'quantity' => $requestedQuantity,
                    'status' => InventoryReservation::STATUS_RESERVED,
                ]);

                $this->recordTransaction($reference, $variantId, 'reserve', $requestedQuantity);
            }

            return [
                'created' => true,
                'data' => $this->reservationPayload($reference, $this->reservationRows($reference, false)),
            ];
        }, 3);
    }

    // Thực hiện commit.
    public function commit(string $reference): array
    {
        return $this->transition(
            $reference,
            InventoryReservation::STATUS_RESERVED,
            InventoryReservation::STATUS_COMMITTED,
            function (Inventory $inventory, InventoryReservation $reservation): void {
                if (
                    (int) $inventory->reserved_quantity < (int) $reservation->quantity
                    || (int) $inventory->quantity < (int) $reservation->quantity
                ) {
                    throw new InventoryReservationException(
                        'Dữ liệu giữ tồn kho không nhất quán',
                        409,
                        ['product_variant_id' => $reservation->product_variant_id],
                    );
                }

                $inventory->quantity = (int) $inventory->quantity - (int) $reservation->quantity;
                $inventory->reserved_quantity = (int) $inventory->reserved_quantity - (int) $reservation->quantity;
            },
        );
    }

    // Thực hiện release.
    public function release(string $reference): array
    {
        return $this->transition(
            $reference,
            InventoryReservation::STATUS_RESERVED,
            InventoryReservation::STATUS_RELEASED,
            function (Inventory $inventory, InventoryReservation $reservation): void {
                if ((int) $inventory->reserved_quantity < (int) $reservation->quantity) {
                    throw new InventoryReservationException(
                        'Dữ liệu giữ tồn kho không nhất quán',
                        409,
                        ['product_variant_id' => $reservation->product_variant_id],
                    );
                }

                $inventory->reserved_quantity = (int) $inventory->reserved_quantity - (int) $reservation->quantity;
            },
        );
    }

    // Thực hiện restore.
    public function restore(string $reference): array
    {
        return $this->transition(
            $reference,
            InventoryReservation::STATUS_COMMITTED,
            InventoryReservation::STATUS_RESTORED,
            function (Inventory $inventory, InventoryReservation $reservation): void {
                $inventory->quantity = (int) $inventory->quantity + (int) $reservation->quantity;
            },
        );
    }

    // Thực hiện chuyển trạng thái.
    private function transition(
        string $reference,
        string $fromStatus,
        string $toStatus,
        callable $updateInventory,
    ): array {
        return DB::connection('bstore_catalog')->transaction(function () use (
            $reference,
            $fromStatus,
            $toStatus,
            $updateInventory,
        ): array {
            $reservations = $this->reservationRows($reference);

            if ($reservations->isEmpty()) {
                throw new InventoryReservationException('Không tìm thấy yêu cầu giữ tồn kho.', 404);
            }

            $currentStatus = $this->singleStatus($reservations);

            if ($currentStatus === $toStatus) {
                return $this->reservationPayload($reference, $reservations);
            }

            if ($currentStatus !== $fromStatus) {
                throw new InventoryReservationException(
                    "Không thể chuyển trạng thái giữ tồn kho từ {$currentStatus} sang {$toStatus}",
                    409,
                    ['status' => $currentStatus],
                );
            }

            $inventories = $this->lockedInventories($reservations->pluck('product_variant_id')->all());

            foreach ($reservations as $reservation) {
                $inventory = $inventories->get((int) $reservation->product_variant_id);

                if (! $inventory) {
                    throw new InventoryReservationException(
                        'Không tồn tại dữ liệu tồn kho cho biến thể đã được giữ',
                        409,
                        ['product_variant_id' => $reservation->product_variant_id],
                    );
                }

                $updateInventory($inventory, $reservation);
                $inventory->save();
                $reservation->status = $toStatus;
                $reservation->save();

                $this->recordTransaction(
                    $reference,
                    (int) $reservation->product_variant_id,
                    match ($toStatus) {
                        InventoryReservation::STATUS_COMMITTED => 'commit',
                        InventoryReservation::STATUS_RELEASED => 'release',
                        InventoryReservation::STATUS_RESTORED => 'restore',
                        default => $toStatus,
                    },
                    (int) $reservation->quantity,
                );
            }

            return $this->reservationPayload($reference, $reservations->fresh());
        }, 3);
    }

    // Chuẩn hóa mặt hàng.
    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $variantId = (int) ($item['product_variant_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($variantId < 1 || $quantity < 1 || isset($normalized[$variantId])) {
                throw new InventoryReservationException('Sản phẩm giữ tồn kho không hợp lệ hoặc bị trùng lặp.', 422);
            }

            $normalized[$variantId] = $quantity;
        }

        ksort($normalized);

        return $normalized;
    }

    // Thực hiện reservation dòng.
    private function reservationRows(string $reference, bool $lock = true): Collection
    {
        $query = InventoryReservation::query()
            ->where('reference', $reference)
            ->orderBy('product_variant_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    // Thực hiện locked inventories.
    private function lockedInventories(array $variantIds): Collection
    {
        return Inventory::query()
            ->whereIn('product_variant_id', $variantIds)
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_variant_id');
    }

    // Thực hiện assert same mặt hàng.
    private function assertSameItems(Collection $reservations, array $items): void
    {
        $existing = $reservations
            ->mapWithKeys(fn (InventoryReservation $reservation): array => [
                (int) $reservation->product_variant_id => (int) $reservation->quantity,
            ])
            ->all();

        if ($existing !== $items) {
            throw new InventoryReservationException(
                'Mã tham chiếu giữ tồn kho đã tồn tại với danh sách sản phẩm khác',
                409,
            );
        }

        $this->singleStatus($reservations);
    }

    // Thực hiện single trạng thái.
    private function singleStatus(Collection $reservations): string
    {
        $statuses = $reservations->pluck('status')->unique()->values();

        if ($statuses->count() !== 1) {
            throw new InventoryReservationException('Các mục giữ tồn kho có trạng thái không nhất quán', 409);
        }

        return (string) $statuses->first();
    }

    // Thực hiện reservation dữ liệu gửi.
    private function reservationPayload(string $reference, Collection $reservations): array
    {
        return [
            'reference' => $reference,
            'status' => $this->singleStatus($reservations),
            'items' => $reservations->map(fn (InventoryReservation $reservation): array => [
                'product_variant_id' => (int) $reservation->product_variant_id,
                'quantity' => (int) $reservation->quantity,
                'status' => (string) $reservation->status,
            ])->values()->all(),
        ];
    }

    // Thực hiện ghi nhận giao dịch.
    private function recordTransaction(string $reference, int $variantId, string $type, int $quantity): void
    {
        InventoryTransaction::create([
            'product_variant_id' => $variantId,
            'type' => $type,
            'quantity' => $quantity,
            'reference' => $reference,
            'note' => "Inventory reservation {$reference}: {$type}",
            'created_by' => null,
        ]);
    }
}
