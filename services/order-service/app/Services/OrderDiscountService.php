<?php

namespace App\Services;

use App\Models\Discount;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderDiscountService
{
    /**
     * Resolve discounts under the current database lock and increment usage.
     */
    public function resolve(array $requestedDiscounts, float $subtotal, int $customerId): array
    {
        return $this->resolveDiscounts($requestedDiscounts, $subtotal, $customerId, true);
    }

    /**
     * Validate and calculate discounts without consuming a usage.
     */
    public function preview(array $requestedDiscounts, float $subtotal, int $customerId): array
    {
        return $this->resolveDiscounts($requestedDiscounts, $subtotal, $customerId, false);
    }

    private function resolveDiscounts(
        array $requestedDiscounts,
        float $subtotal,
        int $customerId,
        bool $consume,
    ): array {
        if ($requestedDiscounts === []) {
            return [];
        }

        if (! Schema::connection('bstore_order')->hasTable('discounts')) {
            throw ValidationException::withMessages([
                'discounts' => ['He thong ma giam gia chua san sang'],
            ]);
        }

        $resolved = [];
        $seen = [];

        foreach ($requestedDiscounts as $requested) {
            $query = Discount::query();
            if ($consume) {
                $query->lockForUpdate();
            }
            $discountId = (int) ($requested['discount_id'] ?? 0);
            $discountCode = trim((string) ($requested['discount_code'] ?? ''));

            if ($discountId > 0) {
                $query->whereKey($discountId);
            } elseif ($discountCode !== '') {
                $query->whereRaw('LOWER(code) = ?', [strtolower($discountCode)]);
            } else {
                throw ValidationException::withMessages([
                    'discounts' => ['Ma giam gia khong hop le'],
                ]);
            }

            $discount = $query->first();

            if (! $discount || ($discountCode !== '' && strcasecmp((string) $discount->code, $discountCode) !== 0)) {
                throw ValidationException::withMessages([
                    'discounts' => ['Khong tim thay ma giam gia hop le'],
                ]);
            }

            if (isset($seen[$discount->id])) {
                throw ValidationException::withMessages([
                    'discounts' => ['Khong duoc ap dung trung ma giam gia'],
                ]);
            }

            $seen[$discount->id] = true;
            $this->ensureUsable($discount, $subtotal, $customerId);

            $amount = match (strtolower((string) $discount->type)) {
                'percent', 'percentage' => $subtotal * min(max((float) $discount->value, 0), 100) / 100,
                'fixed', 'amount', 'flat', 'fixed_amount' => max((float) $discount->value, 0),
                default => throw ValidationException::withMessages([
                    'discounts' => ["Loai ma giam gia {$discount->code} khong duoc ho tro"],
                ]),
            };

            if (
                in_array(strtolower((string) $discount->type), ['percent', 'percentage'], true)
                && $discount->max_discount_amount !== null
            ) {
                $amount = min($amount, (float) $discount->max_discount_amount);
            }

            $resolved[] = [
                'discount_id' => (int) $discount->id,
                'discount_code' => (string) $discount->code,
                'discount_amount' => min($amount, max($subtotal, 0)),
            ];

            if ($consume) {
                $discount->used_count = (int) $discount->used_count + 1;
                $discount->save();
            }
        }

        $remaining = max($subtotal, 0);

        return collect($resolved)->map(function (array $discount) use (&$remaining): array {
            $discount['discount_amount'] = min((float) $discount['discount_amount'], $remaining);
            $remaining -= $discount['discount_amount'];

            return $discount;
        })->all();
    }

    private function ensureUsable(Discount $discount, float $subtotal, int $customerId): void
    {
        if (strtolower((string) $discount->status) !== 'active') {
            throw ValidationException::withMessages([
                'discounts' => ["Ma giam gia {$discount->code} khong hoat dong"],
            ]);
        }

        $now = now();

        if ($discount->start_date && $now->lt($discount->start_date)) {
            throw ValidationException::withMessages([
                'discounts' => ["Ma giam gia {$discount->code} chua co hieu luc"],
            ]);
        }

        if ($discount->end_date && $now->gt($discount->end_date)) {
            throw ValidationException::withMessages([
                'discounts' => ["Ma giam gia {$discount->code} da het han"],
            ]);
        }

        if ((float) $discount->min_order_amount > $subtotal) {
            throw ValidationException::withMessages([
                'discounts' => ["Don hang chua dat gia tri toi thieu cua ma {$discount->code}"],
            ]);
        }

        if ((int) $discount->usage_limit > 0 && (int) $discount->used_count >= (int) $discount->usage_limit) {
            throw ValidationException::withMessages([
                'discounts' => ["Ma giam gia {$discount->code} da het luot su dung"],
            ]);
        }

        if ((int) $discount->usage_limit_per_customer > 0) {
            $customerUsage = $discount->orderDiscounts()
                ->whereHas('order', fn ($query) => $query->where('user_id', $customerId))
                ->count();

            if ($customerUsage >= (int) $discount->usage_limit_per_customer) {
                throw ValidationException::withMessages([
                    'discounts' => ["Khach hang da het luot su dung ma {$discount->code}"],
                ]);
            }
        }
    }
}
