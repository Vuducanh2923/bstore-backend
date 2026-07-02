<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartService
{
    public function __construct(private readonly CatalogPricingService $catalogPricingService) {}

    public function create(array $data): Cart
    {
        return DB::connection('bstore_order')->transaction(function () use ($data) {
            $items = $this->catalogPricingService->applyCurrentPrices($data['items'] ?? []);
            unset($data['items']);

            $cart = Cart::create($data);

            foreach ($items as $item) {
                $item['cart_id'] = $cart->id;
                $item['subtotal'] = $this->subtotal($item);

                CartItem::create($item);
            }

            return $cart->fresh('items');
        });
    }

    public function find(int $id): ?Cart
    {
        return Cart::with('items')->find($id);
    }

    public function clearForPaidOrder(int $orderId): array
    {
        return DB::connection('bstore_order')->transaction(function () use ($orderId) {
            $order = Order::query()->find($orderId);

            if (! $order) {
                Log::warning('order.cart_clear_after_payment.order_not_found', [
                    'order_id' => $orderId,
                ]);

                return [
                    'order_found' => false,
                    'cleared' => false,
                    'order_id' => $orderId,
                    'user_id' => null,
                    'cart_ids' => [],
                    'deleted_items' => 0,
                ];
            }

            $cartIds = Cart::query()
                ->where('user_id', $order->user_id)
                ->where(function ($query): void {
                    $query->whereNull('status')
                        ->orWhere('status', 'active');
                })
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values();

            $deletedItems = $cartIds->isEmpty()
                ? 0
                : CartItem::query()->whereIn('cart_id', $cartIds->all())->delete();

            $result = [
                'order_found' => true,
                'cleared' => true,
                'order_id' => (int) $order->id,
                'user_id' => (int) $order->user_id,
                'cart_ids' => $cartIds->all(),
                'deleted_items' => $deletedItems,
            ];

            Log::info('order.cart_clear_after_payment.completed', [
                'order_id' => $result['order_id'],
                'user_id' => $result['user_id'],
                'cart_count' => count($result['cart_ids']),
                'deleted_items' => $result['deleted_items'],
            ]);

            return $result;
        });
    }

    private function subtotal(array $item): float
    {
        return (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
    }
}
