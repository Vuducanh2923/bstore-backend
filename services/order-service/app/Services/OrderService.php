<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly CatalogPricingService $catalogPricingService,
        private readonly OrderNotificationService $notifications,
    ) {}

    public function all(): Collection
    {
        return $this->newestFirst(Order::with(['items', 'discounts']))->get();
    }

    public function adminOrders(): Collection
    {
        return $this->newestFirst(Order::query())->get();
    }

    public function findForAdmin(int $orderId): ?Order
    {
        return Order::with('items')->find($orderId);
    }

    public function updateStatus(int $orderId, string $status): ?Order
    {
        $order = Order::with('items')->find($orderId);

        if (! $order) {
            return null;
        }

        $order->status = $status;
        $order->save();

        $freshOrder = $order->fresh('items') ?? $order;
        $this->notifications->sendStatusUpdated($freshOrder);

        return $freshOrder;
    }

    public function updatePaymentStatus(int $orderId, array $data): ?Order
    {
        return DB::connection('bstore_order')->transaction(function () use ($orderId, $data) {
            $order = Order::query()->find($orderId);

            if (! $order) {
                return null;
            }

            $order->payment_status = $data['payment_status'];
            $order->status = $data['status'] ?? ($data['payment_status'] === 'paid' ? 'confirmed' : $order->status);

            if (Schema::connection('bstore_order')->hasColumn('orders', 'payment_method') && array_key_exists('payment_method', $data)) {
                $order->payment_method = $data['payment_method'];
            }

            if (Schema::connection('bstore_order')->hasColumn('orders', 'paid_at') && array_key_exists('paid_at', $data)) {
                $order->setAttribute('paid_at', $data['paid_at']);
            }

            $order->save();

            return $order->fresh() ?? $order;
        });
    }

    public function forCustomer(int $userId): Collection
    {
        return $this->newestFirst(
            Order::with('items')->where('user_id', $userId)
        )->get();
    }

    public function findForCustomer(int $userId, int $orderId): ?Order
    {
        return Order::with('items')
            ->where('user_id', $userId)
            ->find($orderId);
    }

    public function create(array $data): Order
    {
        $order = DB::connection('bstore_order')->transaction(function () use ($data) {
            $items = $this->catalogPricingService->applyCurrentPrices($data['items'] ?? []);
            $discounts = $data['discounts'] ?? [];
            $hasItems = $items !== [];
            $hasDiscounts = $discounts !== [];

            unset($data['items'], $data['discounts']);

            $itemTotal = collect($items)->sum(fn (array $item) => $this->subtotal($item));
            $discountTotal = collect($discounts)->sum(fn (array $discount) => (float) ($discount['discount_amount'] ?? 0));
            $shippingFee = (float) ($data['shipping_fee'] ?? 0);

            $data['order_code'] = $data['order_code'] ?? $this->orderCode();
            $data['total_amount'] = $hasItems ? $itemTotal : ($data['total_amount'] ?? $itemTotal);
            $data['discount_amount'] = $hasDiscounts ? $discountTotal : ($data['discount_amount'] ?? $discountTotal);
            $data['final_amount'] = ($hasItems || $hasDiscounts)
                ? max((float) $data['total_amount'] - (float) $data['discount_amount'] + $shippingFee, 0)
                : ($data['final_amount'] ?? max((float) $data['total_amount'] - (float) $data['discount_amount'] + $shippingFee, 0));

            if (strtolower((string) ($data['payment_method'] ?? '')) === 'vnpay' && (float) $data['final_amount'] < 1000) {
                throw ValidationException::withMessages([
                    'final_amount' => ['Don hang thanh toan VNPAY phai co tong tien lon hon hoac bang 1000'],
                ]);
            }

            $order = Order::create($this->payloadForTable($data, 'orders'));

            foreach ($items as $item) {
                $item['order_id'] = $order->id;
                $item['subtotal'] = $this->subtotal($item);

                OrderItem::create($this->payloadForTable($item, 'order_items'));
            }

            foreach ($discounts as $discount) {
                $discount['order_id'] = $order->id;

                OrderDiscount::create($discount);
            }

            return $order->fresh(['items', 'discounts']);
        });

        $this->notifications->sendCreated($order);

        return $order;
    }

    public function serializeAdminOrder(Order $order, bool $withItems = true): array
    {
        if ($withItems) {
            $order->loadMissing('items');
        }

        $data = [
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'user_id' => $order->user_id,
            'customer_name' => $order->receiver_name,
            'customer_email' => $order->receiver_email,
            'customer_phone' => $order->receiver_phone,
            'shipping_address' => $order->shipping_address,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->getAttribute('payment_method'),
            'subtotal' => $this->money($this->orderSubtotal($order)),
            'discount_amount' => $this->money($order->discount_amount),
            'shipping_fee' => $this->money($order->getAttribute('shipping_fee') ?? 0),
            'total_amount' => $this->money($this->orderTotal($order)),
            'created_at' => $order->created_at,
            'updated_at' => $order->getAttribute('updated_at'),
        ];

        if ($withItems) {
            $snapshots = $this->catalogSnapshotsForItems($order->items);

            $data['items'] = $order->items
                ->map(fn (OrderItem $item) => $this->serializeAdminOrderItem($item, $snapshots))
                ->values()
                ->all();
        }

        return $data;
    }

    public function serializeAdminOrders(iterable $orders, bool $withItems = false): array
    {
        return collect($orders)
            ->map(fn (Order $order) => $this->serializeAdminOrder($order, $withItems))
            ->values()
            ->all();
    }

    public function serializeOrder(Order $order, bool $withItems = true): array
    {
        $data = [
            'id' => $order->id,
            'order_code' => $order->order_code,
            'receiver_name' => $order->receiver_name,
            'receiver_phone' => $order->receiver_phone,
            'receiver_email' => $order->receiver_email,
            'shipping_address' => $order->shipping_address,
            'shipping_method' => $order->shipping_method,
            'total_amount' => $order->total_amount,
            'discount_amount' => $order->discount_amount,
            'final_amount' => $order->final_amount,
            'status' => $order->status,
            'status_label' => $order->statusLabel(),
            'payment_status' => $order->payment_status,
            'payment_status_label' => $order->paymentStatusLabel(),
            'note' => $order->note,
            'created_at' => $order->created_at,
        ];

        if ($withItems) {
            $data['items'] = $order->items;
        }

        return $data;
    }

    public function serializeOrders(iterable $orders, bool $withItems = true): array
    {
        return collect($orders)
            ->map(fn (Order $order) => $this->serializeOrder($order, $withItems))
            ->values()
            ->all();
    }

    private function subtotal(array $item): float
    {
        return (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
    }

    private function orderSubtotal(Order $order): float
    {
        if ($order->total_amount !== null) {
            return (float) $order->total_amount;
        }

        return (float) $order->items->sum(fn (OrderItem $item) => (float) ($item->subtotal ?? 0));
    }

    private function orderTotal(Order $order): float
    {
        if ($order->final_amount !== null) {
            return (float) $order->final_amount;
        }

        return max(
            $this->orderSubtotal($order)
            - (float) ($order->discount_amount ?? 0)
            + (float) ($order->getAttribute('shipping_fee') ?? 0),
            0
        );
    }

    private function serializeAdminOrderItem(OrderItem $item, $snapshots): array
    {
        $snapshot = $snapshots->get((int) $item->product_variant_id, []);
        $productId = $item->getAttribute('product_id') ?? data_get($snapshot, 'product_id');
        $productImage = $item->getAttribute('product_image') ?: data_get($snapshot, 'product_image');
        $totalPrice = $item->subtotal ?? ((float) $item->price * (int) $item->quantity);

        return [
            'product_id' => $productId !== null ? (int) $productId : null,
            'product_name' => $item->product_name,
            'product_image' => $productImage,
            'quantity' => $item->quantity,
            'unit_price' => $this->money($item->price),
            'total_price' => $this->money($totalPrice),
        ];
    }

    private function catalogSnapshotsForItems(iterable $items)
    {
        $variantIds = collect($items)
            ->pluck('product_variant_id')
            ->map(fn ($variantId) => (int) $variantId)
            ->filter(fn (int $variantId) => $variantId > 0)
            ->unique()
            ->values();

        if ($variantIds->isEmpty() || ! $this->catalogTableExists('product_variants')) {
            return collect();
        }

        try {
            $variants = DB::connection('bstore_catalog')
                ->table('product_variants')
                ->whereIn('id', $variantIds->all())
                ->select(['id', 'product_id'])
                ->get()
                ->keyBy(fn (object $variant) => (int) $variant->id);

            $snapshots = $variants->map(fn (object $variant) => [
                'product_id' => (int) $variant->product_id,
                'product_image' => null,
            ]);

            if (! $this->catalogTableExists('product_images')) {
                return $snapshots;
            }

            $images = DB::connection('bstore_catalog')
                ->table('product_images')
                ->whereIn('product_variant_id', $variantIds->all())
                ->orWhereIn('product_id', $variants->pluck('product_id')->all())
                ->orderByDesc('is_thumbnail')
                ->orderBy('id')
                ->select(['product_id', 'product_variant_id', 'image_url'])
                ->get();

            foreach ($images as $image) {
                $imageVariantId = (int) ($image->product_variant_id ?? 0);

                if ($imageVariantId > 0 && $snapshots->has($imageVariantId) && ! $snapshots->get($imageVariantId)['product_image']) {
                    $snapshot = $snapshots->get($imageVariantId);
                    $snapshot['product_image'] = $image->image_url;
                    $snapshots->put($imageVariantId, $snapshot);
                }
            }

            foreach ($snapshots as $variantId => $snapshot) {
                if ($snapshot['product_image']) {
                    continue;
                }

                $productImage = $images->first(
                    fn (object $image) => (int) $image->product_id === (int) $snapshot['product_id']
                );

                if ($productImage) {
                    $snapshot['product_image'] = $productImage->image_url;
                    $snapshots->put($variantId, $snapshot);
                }
            }

            return $snapshots;
        } catch (\Throwable $exception) {
            report($exception);

            return collect();
        }
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    private function payloadForTable(array $data, string $table): array
    {
        $columns = $this->tableColumns($table);

        if ($columns === []) {
            return $data;
        }

        return array_intersect_key($data, array_flip($columns));
    }

    private function tableColumns(string $table): array
    {
        try {
            return Schema::connection('bstore_order')->hasTable($table)
                ? Schema::connection('bstore_order')->getColumnListing($table)
                : [];
        } catch (\Throwable $exception) {
            report($exception);

            return [];
        }
    }

    private function catalogTableExists(string $table): bool
    {
        try {
            return Schema::connection('bstore_catalog')->hasTable($table);
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function orderCode(): string
    {
        return 'ORD'.now()->format('YmdHis').Str::upper(Str::random(4));
    }

    private function newestFirst($query)
    {
        if (Schema::connection('bstore_order')->hasColumn('orders', 'created_at')) {
            $query->orderByDesc('created_at');
        }

        return $query->orderByDesc('id');
    }
}
