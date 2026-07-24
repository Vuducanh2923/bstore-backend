<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItem;
use App\Models\RefundRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    private array $tableColumns = [];

    private array $catalogTableExists = [];

    private ?bool $ordersHasCreatedAt = null;

    public function __construct(
        private readonly CatalogPricingService $catalogPricingService,
        private readonly OrderDiscountService $discountService,
        private readonly InventoryService $inventory,
        private readonly OrderNotificationService $notifications,
        private readonly UserDirectoryService $users,
        private readonly OrderHistoryService $histories,
    ) {}

    public function all(): Collection
    {
        return $this->newestFirst(Order::with(['items', 'discounts']))->get();
    }

    public function adminOrders(array $filters = []): LengthAwarePaginator
    {
        $query = Order::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        $perPage = $this->perPage($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $this->newestFirst($query)->paginate($perPage, ['*'], 'page', $page);
    }

    public function findForAdmin(int $orderId): ?Order
    {
        return Order::with('items')->find($orderId);
    }

    public function assignToStaff(int $orderId, array $actor, ?string $note = null): ?Order
    {
        $order = DB::connection('bstore_order')->transaction(function () use ($orderId, $actor, $note) {
            $order = Order::with('items')->lockForUpdate()->find($orderId);

            if (! $order) {
                return null;
            }

            $actor = $this->users->actor($actor);

            if (! in_array($actor['role'], ['ADMIN', 'STAFF'], true)) {
                throw new AuthorizationException('Khong co quyen nhan xu ly don hang');
            }

            $currentStatus = $this->normalizeStatus((string) $order->status);

            if ($currentStatus !== Order::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => ['Chi co the nhan xu ly don hang dang Pending'],
                ]);
            }

            $this->ensurePaymentAllowsProcessing($order);

            $assignedStaffId = (int) ($order->getAttribute('assigned_staff_id') ?? 0);

            if ($assignedStaffId > 0 && $assignedStaffId !== (int) $actor['id']) {
                throw new AuthorizationException('Don hang da co nhan vien khac phu trach');
            }

            $oldStatus = (string) $order->status;
            $this->setOrderColumn($order, 'assigned_staff_id', $actor['id']);
            $this->setOrderColumn($order, 'assigned_staff_name', $actor['name']);
            $this->setOrderColumn($order, 'assigned_at', now());
            $this->setOrderColumn($order, 'processing_note', $note);
            $this->commitInventory($order);
            $order->status = Order::STATUS_PROCESSING;
            $order->save();

            $this->histories->record(
                (int) $order->id,
                'order_assigned',
                $oldStatus,
                Order::STATUS_PROCESSING,
                $actor,
                $note,
            );

            return $order->fresh('items') ?? $order;
        });

        if ($order) {
            $this->notifications->sendStatusUpdated($order);
        }

        return $order;
    }

    public function updateStatus(int $orderId, string $status, ?array $actor = null, ?string $note = null): ?Order
    {
        $status = $this->normalizeStatus($status);

        if (! in_array($status, Order::WORKFLOW_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['Trang thai don hang khong nam trong luong xu ly'],
            ]);
        }

        $order = DB::connection('bstore_order')->transaction(function () use ($orderId, $status, $actor, $note) {
            $order = Order::with('items')->lockForUpdate()->find($orderId);

            if (! $order) {
                return null;
            }

            $oldStatus = (string) $order->status;
            $currentStatus = $this->normalizeStatus($oldStatus);

            if ($currentStatus === Order::STATUS_DELIVERED) {
                throw ValidationException::withMessages([
                    'status' => ['Don hang Delivered da khoa chinh sua trang thai'],
                ]);
            }

            if ($currentStatus === $status) {
                return $order->fresh('items') ?? $order;
            }

            if (! $this->isNextWorkflowStatus($currentStatus, $status)) {
                throw ValidationException::withMessages([
                    'status' => ['Khong duoc chuyen trang thai don hang nhay buoc hoac quay lui'],
                ]);
            }

            $actorProfile = $actor ? $this->users->actor($actor) : null;
            $this->ensureCanHandleOrder(
                $order,
                $actorProfile,
                allowUnassignedStaff: $currentStatus === Order::STATUS_PENDING && $status === Order::STATUS_PROCESSING,
            );

            if ($currentStatus === Order::STATUS_PENDING && $status === Order::STATUS_PROCESSING && $actorProfile) {
                $this->ensurePaymentAllowsProcessing($order);
                $this->setOrderColumn($order, 'assigned_staff_id', $actorProfile['id']);
                $this->setOrderColumn($order, 'assigned_staff_name', $actorProfile['name']);
                $this->setOrderColumn($order, 'assigned_at', now());
                $this->setOrderColumn($order, 'processing_note', $note);
                $this->commitInventory($order);
            }

            $order->status = $status;
            if ($status === Order::STATUS_DELIVERED) {
                $this->setOrderColumn($order, 'delivered_at', now());
            }
            $order->save();

            $this->histories->record(
                (int) $order->id,
                'status_updated',
                $oldStatus,
                $status,
                $actorProfile,
                $note,
            );

            return $order->fresh('items') ?? $order;
        });

        if ($order) {
            $this->notifications->sendStatusUpdated($order);
        }

        return $order;
    }

    public function updatePaymentStatus(int $orderId, array $data): ?Order
    {
        return DB::connection('bstore_order')->transaction(function () use ($orderId, $data) {
            $order = Order::query()->lockForUpdate()->find($orderId);

            if (! $order) {
                return null;
            }

            $oldPaymentStatus = strtolower((string) $order->payment_status);
            $newPaymentStatus = strtolower((string) $data['payment_status']);
            $this->ensurePaymentTransition($oldPaymentStatus, $newPaymentStatus);

            if ($newPaymentStatus === 'paid') {
                $this->commitInventory($order);
            }

            $order->payment_status = $newPaymentStatus;

            if (Schema::connection('bstore_order')->hasColumn('orders', 'paid_at') && $newPaymentStatus === 'paid') {
                $order->setAttribute('paid_at', $data['paid_at'] ?? $order->getAttribute('paid_at') ?? now());
            }

            $order->save();

            if ($oldPaymentStatus !== $newPaymentStatus) {
                $this->histories->record(
                    (int) $order->id,
                    'payment_status_updated',
                    (string) $order->status,
                    (string) $order->status,
                    null,
                    "Payment: {$oldPaymentStatus} -> {$newPaymentStatus}",
                );
            }

            return $order->fresh() ?? $order;
        });
    }

    public function forCustomer(int $userId): Collection
    {
        return $this->newestFirst(
            Order::with('items')->where('user_id', $userId)
        )->get();
    }

    public function paginatedForCustomer(int $userId, array $filters = []): Paginator
    {
        $query = Order::with('items')->where('user_id', $userId);
        $perPage = $this->perPage($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $this->newestFirst($query)->simplePaginate($perPage, ['*'], 'page', $page);
    }

    public function findForCustomer(int $userId, int $orderId): ?Order
    {
        return Order::with('items')
            ->where('user_id', $userId)
            ->find($orderId);
    }

    public function paymentContext(int $orderId, ?int $customerId = null): ?array
    {
        $order = Order::query()->find($orderId);

        if (! $order || ($customerId !== null && (int) $order->user_id !== $customerId)) {
            return null;
        }

        $cartId = $order->getAttribute('cart_id');

        if ($cartId === null && $this->orderTableExists('carts')) {
            $cartId = Cart::query()
                ->where('user_id', $order->user_id)
                ->where(function ($query): void {
                    $query->whereNull('status')->orWhere('status', 'active');
                })
                ->orderByDesc('id')
                ->value('id');
        }

        return [
            'order_id' => (int) $order->id,
            'customer_id' => (int) $order->user_id,
            'user_id' => (int) $order->user_id,
            'final_amount' => (float) $order->final_amount,
            'payment_method' => (string) $order->getAttribute('payment_method'),
            'payment_status' => (string) $order->payment_status,
            'order_status' => (string) $order->status,
            'status' => (string) $order->status,
            'cart_id' => $cartId !== null ? (int) $cartId : null,
        ];
    }

    public function requestCancel(int $customerId, int $orderId, string $reason): ?Order
    {
        $order = DB::connection('bstore_order')->transaction(function () use ($customerId, $orderId, $reason) {
            $order = Order::with('items')
                ->where('user_id', $customerId)
                ->lockForUpdate()
                ->find($orderId);

            if (! $order) {
                return null;
            }

            $currentStatus = $this->normalizeStatus((string) $order->status);

            if (! in_array($currentStatus, [Order::STATUS_PENDING, Order::STATUS_PROCESSING], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Khach hang chi duoc huy don o trang thai Pending hoac Processing'],
                ]);
            }

            $oldStatus = (string) $order->status;
            $order->status = Order::STATUS_PENDING_CANCEL;
            $order->cancel_reason = $reason;
            $order->save();

            $this->histories->record(
                (int) $order->id,
                'cancel_requested',
                $oldStatus,
                Order::STATUS_PENDING_CANCEL,
                null,
                $reason,
            );

            return $order->fresh('items') ?? $order;
        });

        if ($order) {
            $this->notifications->sendStatusUpdated($order);
        }

        return $order;
    }

    public function approveCancel(int $orderId, array $actor, ?string $note = null): ?Order
    {
        $refundCreated = null;

        $order = DB::connection('bstore_order')->transaction(function () use ($orderId, $actor, $note, &$refundCreated) {
            $order = Order::with('items')->lockForUpdate()->find($orderId);

            if (! $order) {
                return null;
            }

            if ($this->normalizeStatus((string) $order->status) !== Order::STATUS_PENDING_CANCEL) {
                throw ValidationException::withMessages([
                    'status' => ['Don hang khong o trang thai cho duyet huy'],
                ]);
            }

            $actor = $this->users->actor($actor);
            $this->ensureCanHandleOrder($order, $actor, allowUnassignedStaff: true);

            $oldStatus = (string) $order->status;
            $this->reverseInventoryForCancellation($order);
            $order->status = Order::STATUS_CANCELLED;
            $order->save();

            $this->histories->record(
                (int) $order->id,
                'cancel_approved',
                $oldStatus,
                Order::STATUS_CANCELLED,
                $actor,
                $note,
            );

            if ($this->isOnlinePaid($order)) {
                $refundCreated = $this->createRefundForCancelledOrder($order, $note);
            }

            return $order->fresh('items') ?? $order;
        });

        if ($order) {
            $this->notifications->sendStatusUpdated($order);

            if ($refundCreated) {
                $this->notifications->create(
                    userId: (int) $order->user_id,
                    orderId: (int) $order->id,
                    type: 'refund_created',
                    message: 'Yeu cau hoan tien da duoc tao tu dong cho don hang da huy.',
                    data: ['refund_id' => $refundCreated->id],
                );
            }
        }

        return $order;
    }

    public function rejectCancel(int $orderId, array $actor, ?string $note = null): ?Order
    {
        $order = DB::connection('bstore_order')->transaction(function () use ($orderId, $actor, $note) {
            $order = Order::with('items')->lockForUpdate()->find($orderId);

            if (! $order) {
                return null;
            }

            if ($this->normalizeStatus((string) $order->status) !== Order::STATUS_PENDING_CANCEL) {
                throw ValidationException::withMessages([
                    'status' => ['Don hang khong o trang thai cho duyet huy'],
                ]);
            }

            $actor = $this->users->actor($actor);
            $this->ensureCanHandleOrder($order, $actor, allowUnassignedStaff: true);

            $oldStatus = (string) $order->status;
            $previousStatus = $this->normalizeStatus(
                $this->histories->previousStatusBeforeCancel((int) $order->id)
                    ?? ((int) ($order->getAttribute('assigned_staff_id') ?? 0) > 0 ? Order::STATUS_PROCESSING : Order::STATUS_PENDING)
            );

            if (! in_array($previousStatus, [Order::STATUS_PENDING, Order::STATUS_PROCESSING], true)) {
                $previousStatus = Order::STATUS_PENDING;
            }

            $order->status = $previousStatus;
            $order->save();

            $this->histories->record(
                (int) $order->id,
                'cancel_rejected',
                $oldStatus,
                $previousStatus,
                $actor,
                $note,
            );

            return $order->fresh('items') ?? $order;
        });

        if ($order) {
            $this->notifications->create(
                userId: (int) $order->user_id,
                orderId: (int) $order->id,
                type: 'cancel_rejected',
                message: 'Yeu cau huy don da bi tu choi.',
                data: ['status' => $order->status],
            );
        }

        return $order;
    }

    public function create(array $data): Order
    {
        $items = $this->catalogPricingService->resolveOrderItems($data['items'] ?? []);
        $requestedDiscounts = $data['discounts'] ?? [];
        $orderCode = $this->orderCode();
        $inventoryReference = $orderCode;
        $this->inventory->reserve($inventoryReference, $items);

        try {
            $order = DB::connection('bstore_order')->transaction(function () use ($data, $items, $requestedDiscounts, $orderCode, $inventoryReference) {
                $itemTotal = (float) collect($items)->sum(fn (array $item): float => $this->subtotal($item));
                $discounts = $this->discountService->resolve($requestedDiscounts, $itemTotal);
                $discountTotal = (float) collect($discounts)->sum('discount_amount');
                $shippingFee = $this->shippingFee($itemTotal);
                $finalAmount = max($itemTotal - $discountTotal + $shippingFee, 0);
                $paymentMethod = strtolower((string) ($data['payment_method'] ?? 'cod'));

                if ($paymentMethod === 'vnpay' && $finalAmount < 1000) {
                    throw ValidationException::withMessages([
                        'final_amount' => ['Don hang thanh toan VNPAY phai co tong tien lon hon hoac bang 1000'],
                    ]);
                }

                $orderData = [
                    'user_id' => (int) $data['user_id'],
                    'cart_id' => $this->activeCartId((int) $data['user_id']),
                    'order_code' => $orderCode,
                    'receiver_name' => $data['receiver_name'],
                    'receiver_phone' => $data['receiver_phone'],
                    'receiver_email' => $data['receiver_email'] ?? null,
                    'shipping_address' => $data['shipping_address'],
                    'shipping_method' => $data['shipping_method'],
                    'payment_method' => $paymentMethod,
                    'total_amount' => $itemTotal,
                    'discount_amount' => $discountTotal,
                    'shipping_fee' => $shippingFee,
                    'final_amount' => $finalAmount,
                    'status' => Order::STATUS_PENDING,
                    'payment_status' => 'unpaid',
                    'cancel_reason' => null,
                    'note' => $data['note'] ?? null,
                    'inventory_reference' => $inventoryReference,
                    'inventory_state' => Order::INVENTORY_RESERVED,
                ];
                $order = Order::create($this->payloadForTable($orderData, 'orders'));

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
        } catch (Throwable $exception) {
            try {
                $this->inventory->release($inventoryReference);
            } catch (Throwable $compensationException) {
                report($compensationException);
            }

            throw $exception;
        }

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
            'assigned_staff_id' => $order->getAttribute('assigned_staff_id'),
            'assigned_staff_name' => $order->getAttribute('assigned_staff_name'),
            'assigned_at' => $order->getAttribute('assigned_at'),
            'processing_note' => $order->getAttribute('processing_note'),
            'cancel_reason' => $order->getAttribute('cancel_reason'),
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
            'shipping_fee' => $order->getAttribute('shipping_fee'),
            'payment_method' => $order->getAttribute('payment_method'),
            'total_amount' => $order->total_amount,
            'discount_amount' => $order->discount_amount,
            'final_amount' => $order->final_amount,
            'status' => $order->status,
            'status_label' => $order->statusLabel(),
            'payment_status' => $order->payment_status,
            'payment_status_label' => $order->paymentStatusLabel(),
            'assigned_staff_id' => $order->getAttribute('assigned_staff_id'),
            'assigned_staff_name' => $order->getAttribute('assigned_staff_name'),
            'assigned_at' => $order->getAttribute('assigned_at'),
            'processing_note' => $order->getAttribute('processing_note'),
            'cancel_reason' => $order->getAttribute('cancel_reason'),
            'note' => $order->note,
            'created_at' => $order->created_at,
            'inventory_state' => $order->getAttribute('inventory_state'),
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

    public function normalizeStatus(string $status): string
    {
        $value = Str::snake(trim($status));
        $value = str_replace('-', '_', $value);

        return [
            'confirmed' => Order::STATUS_PROCESSING,
            'packing' => Order::STATUS_SHIPPING,
            'pendingcancel' => Order::STATUS_PENDING_CANCEL,
        ][$value] ?? $value;
    }

    private function isNextWorkflowStatus(string $currentStatus, string $nextStatus): bool
    {
        $currentIndex = array_search($this->normalizeStatus($currentStatus), Order::WORKFLOW_STATUSES, true);
        $nextIndex = array_search($this->normalizeStatus($nextStatus), Order::WORKFLOW_STATUSES, true);

        return $currentIndex !== false
            && $nextIndex !== false
            && $nextIndex === $currentIndex + 1;
    }

    private function ensureCanHandleOrder(Order $order, ?array $actor, bool $allowUnassignedStaff = false): void
    {
        if (! $actor) {
            return;
        }

        if (($actor['role'] ?? null) === 'ADMIN') {
            return;
        }

        if (($actor['role'] ?? null) !== 'STAFF') {
            throw new AuthorizationException('Khong co quyen xu ly don hang');
        }

        $assignedStaffId = (int) ($order->getAttribute('assigned_staff_id') ?? 0);

        if ($assignedStaffId > 0 && $assignedStaffId !== (int) $actor['id']) {
            throw new AuthorizationException('Don hang da co nhan vien khac phu trach');
        }

        if ($assignedStaffId === 0 && ! $allowUnassignedStaff) {
            throw new AuthorizationException('Nhan vien can nhan xu ly don hang truoc khi thao tac');
        }
    }

    private function ensurePaymentAllowsProcessing(Order $order): void
    {
        if ($this->isCashOnDelivery($order)) {
            return;
        }

        if (strtolower((string) $order->payment_status) !== 'paid') {
            throw ValidationException::withMessages([
                'payment_status' => ['Don thanh toan online phai duoc thanh toan truoc khi xu ly'],
            ]);
        }
    }

    private function ensurePaymentTransition(string $current, string $next): void
    {
        if ($current === $next) {
            return;
        }

        $allowed = [
            'unpaid' => ['pending', 'paid', 'failed'],
            'pending' => ['paid', 'failed'],
            'failed' => ['pending', 'paid'],
            'paid' => ['refunded'],
            'refunded' => [],
        ];

        if (! in_array($next, $allowed[$current] ?? [], true)) {
            throw ValidationException::withMessages([
                'payment_status' => ["Khong the chuyen trang thai thanh toan tu {$current} sang {$next}"],
            ]);
        }
    }

    private function commitInventory(Order $order): void
    {
        if (! $this->orderColumnExists('inventory_reference') || ! $this->orderColumnExists('inventory_state')) {
            return;
        }

        $reference = (string) $order->getAttribute('inventory_reference');
        $state = (string) $order->getAttribute('inventory_state');

        if ($reference === '' || $state === Order::INVENTORY_COMMITTED) {
            return;
        }

        if ($state !== Order::INVENTORY_RESERVED) {
            throw ValidationException::withMessages([
                'inventory' => ["Khong the commit ton kho o trang thai {$state}"],
            ]);
        }

        $this->inventory->commit($reference);
        $order->setAttribute('inventory_state', Order::INVENTORY_COMMITTED);
        $this->setOrderColumn($order, 'inventory_updated_at', now());
    }

    private function reverseInventoryForCancellation(Order $order): void
    {
        if (! $this->orderColumnExists('inventory_reference') || ! $this->orderColumnExists('inventory_state')) {
            return;
        }

        $reference = (string) $order->getAttribute('inventory_reference');
        $state = (string) $order->getAttribute('inventory_state');

        if ($reference === '' || in_array($state, [Order::INVENTORY_RELEASED, Order::INVENTORY_RESTORED], true)) {
            return;
        }

        if ($state === Order::INVENTORY_RESERVED) {
            $this->inventory->release($reference);
            $order->setAttribute('inventory_state', Order::INVENTORY_RELEASED);
        } elseif ($state === Order::INVENTORY_COMMITTED) {
            $this->inventory->restore($reference);
            $order->setAttribute('inventory_state', Order::INVENTORY_RESTORED);
        } else {
            throw ValidationException::withMessages([
                'inventory' => ["Khong the hoan ton kho o trang thai {$state}"],
            ]);
        }

        $this->setOrderColumn($order, 'inventory_updated_at', now());
    }

    private function isCashOnDelivery(Order $order): bool
    {
        return in_array(strtolower((string) $order->getAttribute('payment_method')), [
            'cod',
            'cash',
            'cash_on_delivery',
        ], true);
    }

    private function shippingFee(float $subtotal): float
    {
        $flatFee = max((float) config('order.shipping.flat_fee', 30000), 0);
        $freeThreshold = max((float) config('order.shipping.free_threshold', 20000000), 0);

        return $freeThreshold > 0 && $subtotal >= $freeThreshold ? 0.0 : $flatFee;
    }

    private function activeCartId(int $userId): ?int
    {
        if (! $this->orderTableExists('carts')) {
            return null;
        }

        $id = Cart::query()
            ->where('user_id', $userId)
            ->where(function ($query): void {
                $query->whereNull('status')->orWhere('status', 'active');
            })
            ->orderByDesc('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function setOrderColumn(Order $order, string $column, mixed $value): void
    {
        if ($this->orderColumnExists($column)) {
            $order->setAttribute($column, $value);
        }
    }

    private function orderColumnExists(string $column): bool
    {
        return in_array($column, $this->tableColumns('orders'), true);
    }

    private function isOnlinePaid(Order $order): bool
    {
        if ($order->payment_status !== 'paid') {
            return false;
        }

        $paymentMethod = strtolower((string) $order->getAttribute('payment_method'));

        return $paymentMethod !== ''
            && ! in_array($paymentMethod, ['cod', 'cash', 'cash_on_delivery'], true);
    }

    private function createRefundForCancelledOrder(Order $order, ?string $note = null): ?RefundRequest
    {
        if (! $this->orderTableExists('refund_requests')) {
            return null;
        }

        $existing = RefundRequest::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [
                RefundRequest::STATUS_PENDING,
                RefundRequest::STATUS_APPROVED,
                RefundRequest::STATUS_REFUNDING,
                RefundRequest::STATUS_REFUNDED,
            ])
            ->first();

        if ($existing) {
            return $existing;
        }

        $refund = RefundRequest::create([
            'order_id' => $order->id,
            'customer_id' => $order->user_id,
            'reason' => trim('Hoan tien do huy don. '.$order->cancel_reason),
            'amount' => $order->final_amount ?? 0,
            'status' => RefundRequest::STATUS_PENDING,
            'admin_note' => $note,
        ]);

        $this->histories->record(
            (int) $order->id,
            'refund_requested',
            (string) $order->status,
            (string) $order->status,
            null,
            $refund->reason,
        );

        return $refund;
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
        } catch (Throwable $exception) {
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
        if (array_key_exists($table, $this->tableColumns)) {
            return $this->tableColumns[$table];
        }

        try {
            return $this->tableColumns[$table] = Schema::connection('bstore_order')->hasTable($table)
                ? Schema::connection('bstore_order')->getColumnListing($table)
                : [];
        } catch (Throwable $exception) {
            report($exception);

            return $this->tableColumns[$table] = [];
        }
    }

    private function orderTableExists(string $table): bool
    {
        try {
            return Schema::connection('bstore_order')->hasTable($table);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function catalogTableExists(string $table): bool
    {
        if (array_key_exists($table, $this->catalogTableExists)) {
            return $this->catalogTableExists[$table];
        }

        try {
            return $this->catalogTableExists[$table] = Schema::connection('bstore_catalog')->hasTable($table);
        } catch (Throwable $exception) {
            report($exception);

            return $this->catalogTableExists[$table] = false;
        }
    }

    private function orderCode(): string
    {
        return 'ORD'.now()->format('YmdHis').Str::upper(Str::random(4));
    }

    private function newestFirst($query)
    {
        $this->ordersHasCreatedAt ??= Schema::connection('bstore_order')->hasColumn('orders', 'created_at');

        if ($this->ordersHasCreatedAt) {
            $query->orderByDesc('created_at');
        }

        return $query->orderByDesc('id');
    }

    private function perPage(array $filters): int
    {
        return min(
            self::MAX_PER_PAGE,
            max(1, (int) ($filters['limit'] ?? $filters['per_page'] ?? self::DEFAULT_PER_PAGE))
        );
    }
}
