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

    // Xây dựng hoặc chuyển đổi discounts.
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
                'discounts' => ['Hệ thống mã giảm giá chưa sẵn sàng'],
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
                    'discounts' => ['Mã giảm giá không hợp lệ'],
                ]);
            }

            $discount = $query->first();

            if (! $discount || ($discountCode !== '' && strcasecmp((string) $discount->code, $discountCode) !== 0)) {
                throw ValidationException::withMessages([
                    'discounts' => ['Không tìm thấy mã giảm giá hợp lệ'],
                ]);
            }

            if (isset($seen[$discount->id])) {
                throw ValidationException::withMessages([
                    'discounts' => ['Không được áp dụng trùng mã giảm giá'],
                ]);
            }

            $seen[$discount->id] = true;
            $this->ensureUsable($discount, $subtotal, $customerId);

            $amount = match (strtolower((string) $discount->type)) {
                'percent', 'percentage' => $subtotal * min(max((float) $discount->value, 0), 100) / 100,
                'fixed', 'amount', 'flat', 'fixed_amount' => max((float) $discount->value, 0),
                default => throw ValidationException::withMessages([
                    'discounts' => ["Loại mã giảm giá {$discount->code} không được hỗ trợ"],
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

    // Kiểm tra usable.
    private function ensureUsable(Discount $discount, float $subtotal, int $customerId): void
    {
        if (strtolower((string) $discount->status) !== 'active') {
            throw ValidationException::withMessages([
                'discounts' => ["Mã giảm giá {$discount->code} không hoạt động"],
            ]);
        }

        $now = now();

        if ($discount->start_date && $now->lt($discount->start_date)) {
            throw ValidationException::withMessages([
                'discounts' => ["Mã giảm giá {$discount->code} chưa có hiệu lực"],
            ]);
        }

        if ($discount->end_date && $now->gt($discount->end_date)) {
            throw ValidationException::withMessages([
                'discounts' => ["Mã giảm giá {$discount->code} đã hết hạn"],
            ]);
        }

        if ((float) $discount->min_order_amount > $subtotal) {
            throw ValidationException::withMessages([
                'discounts' => ["Đơn hàng chưa đạt giá trị tối thiểu của mã {$discount->code}"],
            ]);
        }

        if ((int) $discount->usage_limit > 0 && (int) $discount->used_count >= (int) $discount->usage_limit) {
            throw ValidationException::withMessages([
                'discounts' => ["Mã giảm giá {$discount->code} đã hết lượt sử dụng"],
            ]);
        }

        if ((int) $discount->usage_limit_per_customer > 0) {
            $customerUsage = $discount->orderDiscounts()
                ->whereHas('order', fn ($query) => $query->where('user_id', $customerId))
                ->count();

            if ($customerUsage >= (int) $discount->usage_limit_per_customer) {
                throw ValidationException::withMessages([
                    'discounts' => ["Khách hàng đã hết lượt sử dụng mã {$discount->code}"],
                ]);
            }
        }
    }
}
