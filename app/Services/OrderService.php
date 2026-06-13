<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function all(): Collection
    {
        return Order::with(['items', 'discounts'])->orderByDesc('id')->get();
    }

    public function create(array $data): Order
    {
        return DB::connection('bstore_order')->transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            $discounts = $data['discounts'] ?? [];

            unset($data['items'], $data['discounts']);

            $itemTotal = collect($items)->sum(fn (array $item) => $this->subtotal($item));
            $discountTotal = collect($discounts)->sum(fn (array $discount) => (float) ($discount['discount_amount'] ?? 0));

            $data['order_code'] = $data['order_code'] ?? $this->orderCode();
            $data['total_amount'] = $data['total_amount'] ?? $itemTotal;
            $data['discount_amount'] = $data['discount_amount'] ?? $discountTotal;
            $data['final_amount'] = $data['final_amount'] ?? max((float) $data['total_amount'] - (float) $data['discount_amount'], 0);

            $order = Order::create($data);

            foreach ($items as $item) {
                $item['order_id'] = $order->id;
                $item['subtotal'] = $item['subtotal'] ?? $this->subtotal($item);

                OrderItem::create($item);
            }

            foreach ($discounts as $discount) {
                $discount['order_id'] = $order->id;

                OrderDiscount::create($discount);
            }

            return $order->fresh(['items', 'discounts']);
        });
    }

    private function subtotal(array $item): float
    {
        return (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
    }

    private function orderCode(): string
    {
        return 'ORD'.now()->format('YmdHis').Str::upper(Str::random(4));
    }
}
