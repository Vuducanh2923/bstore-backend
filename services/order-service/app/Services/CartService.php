<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        return $this->attachAvailableQuantities(Cart::with('items')->find($id));
    }

    public function forUser(int $userId)
    {
        $carts = Cart::with('items')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();

        $carts->each(fn (Cart $cart) => $this->attachAvailableQuantities($cart));

        return $carts;
    }

    public function findForUser(int $userId, int $id): ?Cart
    {
        return $this->attachAvailableQuantities(Cart::with('items')
            ->where('user_id', $userId)
            ->find($id));
    }

    private function attachAvailableQuantities(?Cart $cart): ?Cart
    {
        if (! $cart || ! $cart->relationLoaded('items') || $cart->items->isEmpty()) {
            return $cart;
        }

        try {
            if (! Schema::connection('bstore_catalog')->hasTable('inventories')) {
                return $cart;
            }

            $available = DB::connection('bstore_catalog')
                ->table('inventories')
                ->whereIn('product_variant_id', $cart->items->pluck('product_variant_id')->all())
                ->get(['product_variant_id', 'quantity', 'reserved_quantity'])
                ->mapWithKeys(fn (object $row): array => [
                    (int) $row->product_variant_id => max(
                        0,
                        (int) $row->quantity - (int) ($row->reserved_quantity ?? 0),
                    ),
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return $cart;
        }

        $cart->items->each(function (CartItem $item) use ($available): void {
            $variantId = (int) $item->product_variant_id;

            if ($available->has($variantId)) {
                $item->setAttribute('available_quantity', (int) $available->get($variantId));
            }
        });

        return $cart;
    }

    public function addItem(int $userId, array $data): CartItem
    {
        return DB::connection('bstore_order')->transaction(function () use ($userId, $data) {
            $cart = Cart::query()
                ->where('user_id', $userId)
                ->where(function ($query): void {
                    $query->whereNull('status')->orWhere('status', 'active');
                })
                ->lockForUpdate()
                ->find((int) $data['cart_id']);

            if (! $cart) {
                throw ValidationException::withMessages([
                    'cart_id' => ['Khong tim thay gio hang dang hoat dong cua khach hang'],
                ]);
            }

            $snapshot = $this->catalogPricingService->resolveOrderItems([[
                'product_variant_id' => (int) $data['product_variant_id'],
                'quantity' => (int) $data['quantity'],
            ]])[0];
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_variant_id', $snapshot['product_variant_id'])
                ->lockForUpdate()
                ->first();

            if ($item) {
                $snapshot['quantity'] += (int) $item->quantity;
                $item->fill($snapshot);
                $item->subtotal = $this->subtotal($snapshot);
                $item->save();

                return $item->fresh() ?? $item;
            }

            $snapshot['cart_id'] = $cart->id;
            $snapshot['subtotal'] = $this->subtotal($snapshot);

            return CartItem::create($snapshot);
        });
    }

    public function updateItem(int $userId, int $itemId, int $quantity): ?CartItem
    {
        return DB::connection('bstore_order')->transaction(function () use ($userId, $itemId, $quantity) {
            $item = CartItem::query()
                ->whereHas('cart', fn ($query) => $query->where('user_id', $userId))
                ->lockForUpdate()
                ->find($itemId);

            if (! $item) {
                return null;
            }

            $snapshot = $this->catalogPricingService->resolveOrderItems([[
                'product_variant_id' => (int) $item->product_variant_id,
                'quantity' => $quantity,
            ]])[0];
            $item->fill($snapshot);
            $item->subtotal = $this->subtotal($snapshot);
            $item->save();

            return $item->fresh() ?? $item;
        });
    }

    public function deleteItem(int $userId, int $itemId): bool
    {
        $item = CartItem::query()
            ->whereHas('cart', fn ($query) => $query->where('user_id', $userId))
            ->find($itemId);

        return $item ? (bool) $item->delete() : false;
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
                    'paid' => false,
                    'order_id' => $orderId,
                    'user_id' => null,
                    'cart_ids' => [],
                    'deleted_items' => 0,
                ];
            }

            if (strtolower((string) $order->payment_status) !== 'paid') {
                Log::warning('order.cart_clear_after_payment.not_paid', [
                    'order_id' => $orderId,
                    'payment_status' => $order->payment_status,
                ]);

                return [
                    'order_found' => true,
                    'cleared' => false,
                    'paid' => false,
                    'order_id' => (int) $order->id,
                    'user_id' => (int) $order->user_id,
                    'cart_ids' => [],
                    'deleted_items' => 0,
                ];
            }

            $orderCartId = $order->getAttribute('cart_id');
            $cartIds = Cart::query()
                ->where('user_id', $order->user_id)
                ->when(
                    $orderCartId !== null,
                    fn ($query) => $query->whereKey((int) $orderCartId),
                    fn ($query) => $query->where(function ($query): void {
                        $query->whereNull('status')->orWhere('status', 'active');
                    }),
                )
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values();

            $deletedItems = $cartIds->isEmpty()
                ? 0
                : CartItem::query()->whereIn('cart_id', $cartIds->all())->delete();

            $result = [
                'order_found' => true,
                'cleared' => true,
                'paid' => true,
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
