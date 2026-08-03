<?php

namespace App\Services;

use App\Exceptions\DiscountConflictException;
use App\Models\Discount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DiscountManagementService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly UserDirectoryService $users) {}

    public function create(array $data, int $creatorId): Discount
    {
        return DB::connection('bstore_order')->transaction(function () use ($data, $creatorId): Discount {
            $code = strtoupper(trim($data['code']));
            $exists = Discount::withTrashed()
                ->whereRaw('LOWER(code) = ?', [strtolower($code)])
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw new DiscountConflictException('Ma giam gia da ton tai');
            }

            return Discount::create([
                'code' => $code,
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
                'type' => $data['discount_type'],
                'value' => $data['discount_value'],
                'max_discount_amount' => $data['max_discount_amount'] ?? null,
                'min_order_amount' => $data['min_order_amount'] ?? 0,
                'usage_limit' => $data['usage_limit'] ?? null,
                'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
                'used_count' => 0,
                'start_date' => $data['starts_at'],
                'end_date' => $data['ends_at'],
                'status' => $data['status'] ?? Discount::STATUS_ACTIVE,
                'created_by' => $creatorId,
            ]);
        });
    }

    public function paginated(array $filters): LengthAwarePaginator
    {
        $query = Discount::query()->withCount(['orderDiscounts as orders_count']);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }
        if (! empty($filters['status'])) {
            if ($filters['status'] === Discount::STATUS_EXPIRED) {
                $query->where(fn ($query) => $query
                    ->where('status', Discount::STATUS_EXPIRED)
                    ->orWhere('end_date', '<', now()));
            } elseif ($filters['status'] === Discount::STATUS_ACTIVE) {
                $query->where('status', Discount::STATUS_ACTIVE)
                    ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', now()));
            } else {
                $query->where('status', $filters['status']);
            }
        }
        if (! empty($filters['discount_type'])) {
            $query->where('type', $filters['discount_type']);
        }

        $now = now();
        match ($filters['validity'] ?? null) {
            'effective' => $query->where('status', Discount::STATUS_ACTIVE)
                ->where('start_date', '<=', $now)->where('end_date', '>=', $now),
            'expiring' => $query->where('status', Discount::STATUS_ACTIVE)
                ->whereBetween('end_date', [$now, $now->copy()->addDays(7)]),
            'expired' => $query->where(fn ($query) => $query
                ->where('status', Discount::STATUS_EXPIRED)->orWhere('end_date', '<', $now)),
            default => null,
        };

        $sortBy = match ($filters['sort_by'] ?? null) {
            'starts_at' => 'start_date',
            'ends_at' => 'end_date',
            default => 'created_at',
        };
        $direction = strtolower((string) ($filters['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $direction)
            ->orderByDesc('id')
            ->paginate($this->perPage($filters));
    }

    public function find(int $id): ?Discount
    {
        $discount = Discount::withCount(['orderDiscounts as orders_count'])->find($id);

        return $discount ? $this->hydrate($discount) : null;
    }

    public function deleteOrDeactivate(int $id): ?array
    {
        return DB::connection('bstore_order')->transaction(function () use ($id): ?array {
            $discount = Discount::query()->lockForUpdate()->find($id);
            if (! $discount) {
                return null;
            }

            $used = (int) $discount->used_count > 0 || $discount->orderDiscounts()->exists();
            if ($used) {
                $discount->status = Discount::STATUS_INACTIVE;
                if (! $discount->end_date || $discount->end_date->isFuture()) {
                    $discount->end_date = now();
                }
                $discount->save();

                return ['action' => 'deactivated', 'discount' => $this->hydrate($discount->fresh() ?? $discount)];
            }

            $snapshot = clone $discount;
            $discount->delete();

            return ['action' => 'deleted', 'discount' => $snapshot];
        });
    }

    public function deactivate(int $id): ?Discount
    {
        return DB::connection('bstore_order')->transaction(function () use ($id): ?Discount {
            $discount = Discount::query()->lockForUpdate()->find($id);
            if (! $discount) {
                return null;
            }
            $discount->status = Discount::STATUS_INACTIVE;
            if (! $discount->end_date || $discount->end_date->isFuture()) {
                $discount->end_date = now();
            }
            $discount->save();

            return $this->hydrate($discount->fresh() ?? $discount);
        });
    }

    public function hydrate(Discount $discount): Discount
    {
        if (! $discount->getAttribute('orders_count')) {
            $discount->setAttribute('orders_count', $discount->orderDiscounts()->count());
        }
        if ((int) $discount->created_by > 0) {
            $discount->setAttribute('creator_context', $this->users->profile((int) $discount->created_by) ?: null);
        }

        return $discount;
    }

    private function perPage(array $filters): int
    {
        return min(self::MAX_PER_PAGE, max(1, (int) ($filters['per_page'] ?? $filters['limit'] ?? self::DEFAULT_PER_PAGE)));
    }
}
