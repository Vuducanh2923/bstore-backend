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
    private const MAX_ITEM_QUANTITY = 100;

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly CatalogPricingService $catalogPricingService) {}

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
    public function create(array $data): Cart
    {
        return DB::connection('bstore_order')->transaction(function () use ($data) {
            $items = $this->catalogPricingService->applyCurrentPrices($data['items'] ?? []);
            unset($data['items']);

            $cart = Cart::create($data);

            foreach ($items as $item) {
                $this->assertQuantityAvailable(
                    (int) $item['product_variant_id'],
                    (int) $item['quantity'],
                );
                $item['cart_id'] = $cart->id;
                $item['subtotal'] = $this->subtotal($item);

                CartItem::create($item);
            }

            return $this->attachAvailableQuantities($cart->fresh('items'));
        });
    }

    // Lấy toàn bộ dữ liệu.
    public function find(int $id): ?Cart
    {
        return $this->attachAvailableQuantities(Cart::with('items')->find($id));
    }

    // Thực hiện cho người dùng.
    public function forUser(int $userId)
    {
        $carts = Cart::with('items')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();

        $availability = $this->availabilityForVariants(
            $carts
                ->flatMap(fn (Cart $cart) => $cart->items)
                ->pluck('product_variant_id')
                ->all(),
        );

        foreach ($carts as $cart) {
            $this->applyAvailability($cart, $availability);
        }

        return $carts;
    }

    // Lấy cho người dùng.
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

        $availability = $this->availabilityForVariants(
            $cart->items->pluck('product_variant_id')->all(),
        );

        return $this->applyAvailability($cart, $availability);
    }

    // Tạo hoặc lưu mặt hàng.
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
                    'cart_id' => ['Không tìm thấy giỏ hàng đang hoạt động của khách hàng'],
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
                $this->assertQuantityAvailable(
                    (int) $snapshot['product_variant_id'],
                    (int) $snapshot['quantity'],
                );
                $item->fill($snapshot);
                $item->subtotal = $this->subtotal($snapshot);
                $item->save();

                return $this->attachAvailabilityToItem($item->fresh() ?? $item);
            }

            $this->assertQuantityAvailable(
                (int) $snapshot['product_variant_id'],
                (int) $snapshot['quantity'],
            );
            $snapshot['cart_id'] = $cart->id;
            $snapshot['subtotal'] = $this->subtotal($snapshot);

            return $this->attachAvailabilityToItem(CartItem::create($snapshot));
        });
    }

    // Cập nhật mặt hàng.
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

            $this->assertQuantityAvailable((int) $item->product_variant_id, $quantity);
            $snapshot = $this->catalogPricingService->resolveOrderItems([[
                'product_variant_id' => (int) $item->product_variant_id,
                'quantity' => $quantity,
            ]])[0];
            $item->fill($snapshot);
            $item->subtotal = $this->subtotal($snapshot);
            $item->save();

            return $this->attachAvailabilityToItem($item->fresh() ?? $item);
        });
    }

    private function assertQuantityAvailable(int $variantId, int $quantity): void
    {
        if ($quantity > self::MAX_ITEM_QUANTITY) {
            throw ValidationException::withMessages([
                'quantity' => ['Mỗi sản phẩm trong giỏ hàng không được vượt quá 100.'],
            ]);
        }

        $available = $this->availabilityForVariants([$variantId])->get($variantId);

        if ($available !== null && $quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => ["Sản phẩm chỉ còn {$available} sản phẩm trong kho."],
            ]);
        }
    }

    private function attachAvailableQuantities(?Cart $cart): ?Cart
    {
        if (! $cart) {
            return null;
        }

        $availability = $this->availabilityForVariants(
            $cart->items->pluck('product_variant_id')->all(),
        );

        return $this->applyAvailability($cart, $availability);
    }

    private function applyAvailability(Cart $cart, $availability): Cart
    {
        foreach ($cart->items as $item) {
            if ($availability->has((int) $item->product_variant_id)) {
                $item->setAttribute(
                    'available_quantity',
                    $availability->get((int) $item->product_variant_id),
                );
            }
        }

        return $cart;
    }

    private function attachAvailabilityToItem(CartItem $item): CartItem
    {
        $availability = $this->availabilityForVariants([(int) $item->product_variant_id]);

        if ($availability->has((int) $item->product_variant_id)) {
            $item->setAttribute(
                'available_quantity',
                $availability->get((int) $item->product_variant_id),
            );
        }

        return $item;
    }

    private function availabilityForVariants(array $variantIds)
    {
        $variantIds = collect($variantIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($variantIds->isEmpty()) {
            return collect();
        }

        try {
            if (! Schema::connection('bstore_catalog')->hasTable('inventories')) {
                return collect();
            }

            return DB::connection('bstore_catalog')
                ->table('inventories')
                ->whereIn('product_variant_id', $variantIds->all())
                ->get(['product_variant_id', 'quantity', 'reserved_quantity'])
                ->mapWithKeys(fn (object $inventory): array => [
                    (int) $inventory->product_variant_id => max(
                        0,
                        (int) $inventory->quantity - (int) $inventory->reserved_quantity,
                    ),
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return collect();
        }
    }

    // Xóa hoặc hủy mặt hàng.
    public function deleteItem(int $userId, int $itemId): bool
    {
        $item = CartItem::query()
            ->whereHas('cart', fn ($query) => $query->where('user_id', $userId))
            ->find($itemId);

        return $item ? (bool) $item->delete() : false;
    }

    // Làm mới hoặc đặt lại cho paid đơn hàng.
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

    // Thực hiện subtotal.
    private function subtotal(array $item): float
    {
        return (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
    }
}
