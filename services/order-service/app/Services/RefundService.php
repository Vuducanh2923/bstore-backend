<?php

namespace App\Services;

use App\Models\Order;
use App\Models\RefundRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly UserDirectoryService $users,
        private readonly PaymentRefundService $payments,
        private readonly OrderHistoryService $histories,
        private readonly OrderNotificationService $notifications,
    ) {}

    public function paginated(array $filters, array $actor): LengthAwarePaginator
    {
        $actor = $this->users->actor($actor);
        $query = RefundRequest::with('order');

        if ($actor['role'] === 'CUSTOMER') {
            $query->where('customer_id', $actor['id']);
        } elseif (! in_array($actor['role'], ['ADMIN', 'STAFF'], true)) {
            throw new AuthorizationException('Khong co quyen xem yeu cau hoan tien');
        }

        if (! empty($filters['status'])) {
            $query->where('status', strtolower((string) $filters['status']));
        }

        $perPage = min(self::MAX_PER_PAGE, max(1, (int) ($filters['limit'] ?? $filters['per_page'] ?? self::DEFAULT_PER_PAGE)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(int $refundId, array $actor): ?RefundRequest
    {
        $refund = RefundRequest::with('order')->find($refundId);

        if (! $refund) {
            return null;
        }

        $this->ensureCanView($refund, $this->users->actor($actor));

        return $refund;
    }

    public function create(array $data, array $actor): RefundRequest
    {
        $actor = $this->users->actor($actor);

        if ($actor['role'] !== 'CUSTOMER') {
            throw new AuthorizationException('Chi khach hang moi duoc gui yeu cau hoan tien');
        }

        return DB::connection('bstore_order')->transaction(function () use ($data, $actor) {
            $order = Order::query()
                ->where('user_id', $actor['id'])
                ->find((int) $data['order_id']);

            if (! $order) {
                throw ValidationException::withMessages([
                    'order_id' => ['Khong tim thay don hang cua khach hang'],
                ]);
            }

            if (strtolower((string) $order->payment_status) !== 'paid' || strtolower((string) $order->status) !== Order::STATUS_DELIVERED) {
                throw ValidationException::withMessages([
                    'order_id' => ['Chi don hang da giao va da thanh toan moi duoc yeu cau hoan tien'],
                ]);
            }

            if (RefundRequest::query()->where('order_id', $order->id)->exists()) {
                throw ValidationException::withMessages([
                    'order_id' => ['Don hang da co yeu cau hoan tien'],
                ]);
            }

            $amount = (float) ($data['amount'] ?? $order->final_amount ?? 0);

            if ($amount <= 0 || $amount > (float) $order->final_amount) {
                throw ValidationException::withMessages([
                    'amount' => ['So tien hoan phai lon hon 0 va khong vuot qua tong tien don hang'],
                ]);
            }

            $refund = RefundRequest::create([
                'order_id' => $order->id,
                'customer_id' => $actor['id'],
                'reason' => $data['reason'],
                'amount' => $amount,
                'status' => RefundRequest::STATUS_PENDING,
            ]);

            $order->refund_status = Order::REFUND_PENDING;
            $order->save();

            $this->histories->record(
                (int) $order->id,
                'refund_requested',
                (string) $order->status,
                (string) $order->status,
                null,
                $refund->reason,
            );

            $this->notifications->create(
                userId: (int) $order->user_id,
                orderId: (int) $order->id,
                type: 'refund_requested',
                message: 'Yeu cau hoan tien da duoc gui.',
                data: ['refund_id' => $refund->id],
            );

            return $refund->fresh('order') ?? $refund;
        });
    }

    public function approve(int $refundId, array $actor, ?string $note = null): ?RefundRequest
    {
        return $this->transition($refundId, $actor, RefundRequest::STATUS_APPROVED, $note);
    }

    public function reject(int $refundId, array $actor, ?string $note = null): ?RefundRequest
    {
        return $this->transition($refundId, $actor, RefundRequest::STATUS_REJECTED, $note);
    }

    public function markRefunding(int $refundId, array $actor, ?string $note = null): ?RefundRequest
    {
        $actor = $this->users->actor($actor);
        $refund = DB::connection('bstore_order')->transaction(function () use ($refundId, $actor, $note) {
            $refund = RefundRequest::with('order')->lockForUpdate()->find($refundId);

            if (! $refund) {
                return null;
            }

            $this->ensureCanHandle($refund, $actor);

            if ($refund->status === RefundRequest::STATUS_REFUNDED) {
                return $refund;
            }

            if (! in_array($refund->status, [RefundRequest::STATUS_APPROVED, RefundRequest::STATUS_REFUNDING], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Chi yeu cau Approved hoac Refunding moi duoc gui toi nha cung cap'],
                ]);
            }

            if ($refund->status === RefundRequest::STATUS_APPROVED) {
                $refund->status = RefundRequest::STATUS_REFUNDING;
                $refund->admin_note = $note ?? $refund->admin_note;
                $refund->save();

                if ($refund->order) {
                    $refund->order->refund_status = Order::REFUND_PROCESSING;
                    $refund->order->save();
                }

                $this->histories->record(
                    (int) $refund->order_id,
                    'refund_refunding',
                    (string) $refund->order?->status,
                    (string) $refund->order?->status,
                    $actor,
                    $note,
                );
            }

            return $refund->fresh('order') ?? $refund;
        });

        if (! $refund || $refund->status === RefundRequest::STATUS_REFUNDED) {
            return $refund;
        }

        if ($this->isCashOnDelivery($refund->order)) {
            $this->notify($refund);

            return $refund;
        }

        $providerStatus = $this->payments->refund(
            (int) $refund->order_id,
            (int) $refund->id,
            (float) $refund->amount,
            (string) $refund->reason,
            (int) $actor['id'],
        );

        if ($providerStatus === 'refunded') {
            $refund = $this->finalize($refundId, $actor, [
                'refund_method' => (string) $refund->order?->payment_method,
                'admin_note' => $note,
            ]);
        }

        if ($refund) {
            $this->notify($refund);
        }

        return $refund;
    }

    public function complete(int $refundId, array $actor, array $data): ?RefundRequest
    {
        $actor = $this->users->actor($actor);
        $refund = RefundRequest::with('order')->find($refundId);

        if (! $refund) {
            return null;
        }

        $this->ensureCanHandle($refund, $actor);

        if (! $this->isCashOnDelivery($refund->order)) {
            throw ValidationException::withMessages([
                'status' => ['Hoan tien online chi hoan tat theo ket qua tu Payment Service'],
            ]);
        }

        $method = strtolower(trim((string) ($data['refund_method'] ?? '')));

        if (! in_array($method, ['cod', 'cash', 'manual', 'bank_transfer'], true)) {
            throw ValidationException::withMessages([
                'refund_method' => ['Phuong thuc hoan tien COD khong hop le'],
            ]);
        }

        $refund = $this->finalize($refundId, $actor, $data);

        if ($refund) {
            $this->notify($refund);
        }

        return $refund;
    }

    public function serialize(RefundRequest $refund): array
    {
        return [
            'id' => $refund->id,
            'order_id' => $refund->order_id,
            'customer_id' => $refund->customer_id,
            'reason' => $refund->reason,
            'amount' => $refund->amount,
            'status' => $refund->status,
            'approved_by' => $refund->approved_by,
            'approved_at' => $refund->approved_at,
            'refund_method' => $refund->refund_method,
            'refund_transaction' => $refund->refund_transaction,
            'admin_note' => $refund->admin_note,
            'created_at' => $refund->created_at,
            'updated_at' => $refund->updated_at,
        ];
    }

    public function serializeMany(iterable $refunds): array
    {
        return collect($refunds)
            ->map(fn (RefundRequest $refund) => $this->serialize($refund))
            ->values()
            ->all();
    }

    private function transition(int $refundId, array $actor, string $nextStatus, ?string $note): ?RefundRequest
    {
        $refund = DB::connection('bstore_order')->transaction(function () use ($refundId, $actor, $nextStatus, $note) {
            $refund = RefundRequest::with('order')->lockForUpdate()->find($refundId);

            if (! $refund) {
                return null;
            }

            $actor = $this->users->actor($actor);
            $this->ensureCanHandle($refund, $actor);
            $this->ensureRefundTransition($refund->status, $nextStatus);

            $refund->status = $nextStatus;
            $refund->approved_by = $actor['id'];
            $refund->approved_at = now();
            $refund->admin_note = $note;
            $refund->save();

            if ($refund->order) {
                $refund->order->refund_status = $nextStatus === RefundRequest::STATUS_REJECTED
                    ? Order::REFUND_FAILED
                    : Order::REFUND_PENDING;
                $refund->order->save();
            }

            $this->histories->record(
                (int) $refund->order_id,
                'refund_'.$nextStatus,
                (string) $refund->order?->status,
                (string) $refund->order?->status,
                $actor,
                $note,
            );

            return $refund->fresh('order') ?? $refund;
        });

        if ($refund) {
            $this->notifications->create(
                userId: (int) $refund->customer_id,
                orderId: (int) $refund->order_id,
                type: 'refund_'.$refund->status,
                message: $this->messageForStatus($refund->status),
                data: ['refund_id' => $refund->id],
            );
        }

        return $refund;
    }

    private function finalize(int $refundId, array $actor, array $data): ?RefundRequest
    {
        return DB::connection('bstore_order')->transaction(function () use ($refundId, $actor, $data) {
            $refund = RefundRequest::with('order')->lockForUpdate()->find($refundId);

            if (! $refund) {
                return null;
            }

            $this->ensureCanHandle($refund, $actor);

            if ($refund->status === RefundRequest::STATUS_REFUNDED) {
                return $refund;
            }

            if (! in_array($refund->status, [RefundRequest::STATUS_APPROVED, RefundRequest::STATUS_REFUNDING], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Chi yeu cau Approved hoac Refunding moi duoc hoan tat'],
                ]);
            }

            if ($refund->status === RefundRequest::STATUS_APPROVED) {
                $this->histories->record(
                    (int) $refund->order_id,
                    'refund_refunding',
                    (string) $refund->order?->status,
                    (string) $refund->order?->status,
                    $actor,
                    $data['admin_note'] ?? null,
                );
            }

            $refund->status = RefundRequest::STATUS_REFUNDED;
            $refund->approved_by = $refund->approved_by ?: $actor['id'];
            $refund->approved_at = $refund->approved_at ?: now();
            $refund->refund_method = $data['refund_method'] ?? $refund->refund_method;
            $refund->refund_transaction = $data['refund_transaction'] ?? $refund->refund_transaction;
            $refund->admin_note = $data['admin_note'] ?? $refund->admin_note;
            $refund->save();

            $order = $refund->order;

            if ($order) {
                $oldOrderStatus = (string) $order->status;
                $order->payment_status = 'refunded';
                $order->refund_status = Order::REFUND_COMPLETED;
                $order->save();

                $this->histories->record(
                    (int) $order->id,
                    'refund_refunded',
                    $oldOrderStatus,
                    $oldOrderStatus,
                    $actor,
                    $data['admin_note'] ?? null,
                );
            }

            return $refund->fresh('order') ?? $refund;
        });
    }

    private function notify(RefundRequest $refund): void
    {
        $this->notifications->create(
            userId: (int) $refund->customer_id,
            orderId: (int) $refund->order_id,
            type: 'refund_'.$refund->status,
            message: $this->messageForStatus($refund->status),
            data: ['refund_id' => $refund->id],
        );
    }

    private function isCashOnDelivery(?Order $order): bool
    {
        return $order !== null && in_array(strtolower((string) $order->payment_method), [
            'cod',
            'cash',
            'cash_on_delivery',
        ], true);
    }

    private function ensureRefundTransition(string $currentStatus, string $nextStatus): void
    {
        $allowed = [
            RefundRequest::STATUS_PENDING => [
                RefundRequest::STATUS_APPROVED,
                RefundRequest::STATUS_REJECTED,
            ],
            RefundRequest::STATUS_APPROVED => [
                RefundRequest::STATUS_REFUNDING,
                RefundRequest::STATUS_REFUNDED,
            ],
            RefundRequest::STATUS_REFUNDING => [
                RefundRequest::STATUS_REFUNDED,
            ],
        ];

        if (! in_array($nextStatus, $allowed[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ['Trang thai yeu cau hoan tien khong hop le'],
            ]);
        }
    }

    private function ensureCanView(RefundRequest $refund, array $actor): void
    {
        if ($actor['role'] === 'CUSTOMER' && (int) $refund->customer_id !== (int) $actor['id']) {
            throw new AuthorizationException('Khong co quyen xem yeu cau hoan tien nay');
        }

        if (! in_array($actor['role'], ['CUSTOMER', 'ADMIN', 'STAFF'], true)) {
            throw new AuthorizationException('Khong co quyen xem yeu cau hoan tien');
        }
    }

    private function ensureCanHandle(RefundRequest $refund, array $actor): void
    {
        if ($actor['role'] === 'ADMIN') {
            return;
        }

        if ($actor['role'] !== 'STAFF') {
            throw new AuthorizationException('Khong co quyen xu ly yeu cau hoan tien');
        }

        $assignedStaffId = (int) ($refund->order?->getAttribute('assigned_staff_id') ?? 0);

        if ($assignedStaffId <= 0 || $assignedStaffId !== (int) $actor['id']) {
            throw new AuthorizationException('Chi nhan vien phu trach don hang moi duoc xu ly hoan tien');
        }
    }

    private function messageForStatus(string $status): string
    {
        return match ($status) {
            RefundRequest::STATUS_APPROVED => 'Yeu cau hoan tien da duoc duyet.',
            RefundRequest::STATUS_REJECTED => 'Yeu cau hoan tien da bi tu choi.',
            RefundRequest::STATUS_REFUNDING => 'Yeu cau hoan tien dang duoc xu ly.',
            RefundRequest::STATUS_REFUNDED => 'Yeu cau hoan tien da hoan tat.',
            default => 'Yeu cau hoan tien da duoc cap nhat.',
        };
    }
}
