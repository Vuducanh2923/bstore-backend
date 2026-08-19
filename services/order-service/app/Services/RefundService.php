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

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(
        private readonly UserDirectoryService $users,
        private readonly PaymentRefundService $payments,
        private readonly OrderHistoryService $histories,
        private readonly OrderNotificationService $notifications,
    ) {}

    // Thực hiện có phân trang.
    public function paginated(array $filters, array $actor): LengthAwarePaginator
    {
        $actor = $this->users->actor($actor);
        $query = RefundRequest::with('order');

        if ($actor['role'] === 'CUSTOMER') {
            $query->where('customer_id', $actor['id']);
        } elseif (! in_array($actor['role'], ['ADMIN', 'STAFF'], true)) {
            throw new AuthorizationException('Không có quyền xem yêu cầu hoàn tiền');
        }

        if (! empty($filters['status'])) {
            $query->where('status', strtolower((string) $filters['status']));
        }

        $perPage = min(self::MAX_PER_PAGE, max(1, (int) ($filters['limit'] ?? $filters['per_page'] ?? self::DEFAULT_PER_PAGE)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
    }

    // Lấy toàn bộ dữ liệu.
    public function find(int $refundId, array $actor): ?RefundRequest
    {
        $refund = RefundRequest::with('order')->find($refundId);

        if (! $refund) {
            return null;
        }

        $this->ensureCanView($refund, $this->users->actor($actor));

        return $refund;
    }

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
    public function create(array $data, array $actor): RefundRequest
    {
        $actor = $this->users->actor($actor);

        if ($actor['role'] !== 'CUSTOMER') {
            throw new AuthorizationException('Chỉ khách hàng mới được gửi yêu cầu hoàn tiền');
        }

        return DB::connection('bstore_order')->transaction(function () use ($data, $actor) {
            $order = Order::query()
                ->where('user_id', $actor['id'])
                ->find((int) $data['order_id']);

            if (! $order) {
                throw ValidationException::withMessages([
                    'order_id' => ['Không tìm thấy đơn hàng của khách hàng'],
                ]);
            }

            if (strtolower((string) $order->payment_status) !== 'paid' || strtolower((string) $order->status) !== Order::STATUS_DELIVERED) {
                throw ValidationException::withMessages([
                    'order_id' => ['Chỉ đơn hàng đã giao và đã thanh toán mới được yêu cầu hoàn tiền'],
                ]);
            }

            if (RefundRequest::query()->where('order_id', $order->id)->exists()) {
                throw ValidationException::withMessages([
                    'order_id' => ['Đơn hàng đã có yêu cầu hoàn tiền'],
                ]);
            }

            $amount = (float) ($data['amount'] ?? $order->final_amount ?? 0);

            if ($amount <= 0 || $amount > (float) $order->final_amount) {
                throw ValidationException::withMessages([
                    'amount' => ['Số tiền hoàn phải lớn hơn 0 và không vượt quá tổng tiền đơn hàng'],
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
                message: 'Yêu cầu hoàn tiền đã được gửi.',
                data: ['refund_id' => $refund->id],
            );

            return $refund->fresh('order') ?? $refund;
        });
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function approve(int $refundId, array $actor, ?string $note = null): ?RefundRequest
    {
        return $this->transition($refundId, $actor, RefundRequest::STATUS_APPROVED, $note);
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function reject(int $refundId, array $actor, ?string $note = null): ?RefundRequest
    {
        return $this->transition($refundId, $actor, RefundRequest::STATUS_REJECTED, $note);
    }

    // Thực hiện mark refunding.
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
                    'status' => ['Chỉ yêu cầu Approved hoặc Refunding mới được gửi tới nhà cung cấp'],
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

    // Thực hiện complete.
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
                'status' => ['Hoàn tiền trực tuyến chỉ hoàn tất theo kết quả từ Dịch vụ thanh toán'],
            ]);
        }

        $method = strtolower(trim((string) ($data['refund_method'] ?? '')));

        if (! in_array($method, ['cod', 'cash', 'manual', 'bank_transfer'], true)) {
            throw ValidationException::withMessages([
                'refund_method' => ['Phương thức hoàn tiền COD không hợp lệ'],
            ]);
        }

        $refund = $this->finalize($refundId, $actor, $data);

        if ($refund) {
            $this->notify($refund);
        }

        return $refund;
    }

    // Thực hiện chuyển thành chuỗi.
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

    // Thực hiện chuyển thành chuỗi many.
    public function serializeMany(iterable $refunds): array
    {
        return collect($refunds)
            ->map(fn (RefundRequest $refund) => $this->serialize($refund))
            ->values()
            ->all();
    }

    // Thực hiện chuyển trạng thái.
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

    // Thực hiện finalize.
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
                    'status' => ['Chỉ yêu cầu Approved hoặc Refunding mới được hoàn tất'],
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

    // Gửi hoặc phát dữ liệu theo nghiệp vụ của hàm.
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

    // Kiểm tra cash on delivery.
    private function isCashOnDelivery(?Order $order): bool
    {
        return $order !== null && in_array(strtolower((string) $order->payment_method), [
            'cod',
            'cash',
            'cash_on_delivery',
        ], true);
    }

    // Kiểm tra hoàn tiền chuyển trạng thái.
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
                'status' => ['Trạng thái yêu cầu hoàn tiền không hợp lệ'],
            ]);
        }
    }

    // Kiểm tra can view.
    private function ensureCanView(RefundRequest $refund, array $actor): void
    {
        if ($actor['role'] === 'CUSTOMER' && (int) $refund->customer_id !== (int) $actor['id']) {
            throw new AuthorizationException('Không có quyền xem yêu cầu hoàn tiền này');
        }

        if (! in_array($actor['role'], ['CUSTOMER', 'ADMIN', 'STAFF'], true)) {
            throw new AuthorizationException('Không có quyền xem yêu cầu hoàn tiền');
        }
    }

    // Kiểm tra can handle.
    private function ensureCanHandle(RefundRequest $refund, array $actor): void
    {
        if ($actor['role'] === 'ADMIN') {
            return;
        }

        if ($actor['role'] !== 'STAFF') {
            throw new AuthorizationException('Không có quyền xử lý yêu cầu hoàn tiền');
        }

        $assignedStaffId = (int) ($refund->order?->getAttribute('assigned_staff_id') ?? 0);

        if ($assignedStaffId <= 0 || $assignedStaffId !== (int) $actor['id']) {
            throw new AuthorizationException('Chỉ nhân viên phụ trách đơn hàng mới được xử lý hoàn tiền');
        }
    }

    // Thực hiện thông báo cho trạng thái.
    private function messageForStatus(string $status): string
    {
        return match ($status) {
            RefundRequest::STATUS_APPROVED => 'Yêu cầu hoàn tiền đã được duyệt.',
            RefundRequest::STATUS_REJECTED => 'Yêu cầu hoàn tiền đã bị từ chối.',
            RefundRequest::STATUS_REFUNDING => 'Yêu cầu hoàn tiền đang được xử lý.',
            RefundRequest::STATUS_REFUNDED => 'Yêu cầu hoàn tiền đã hoàn tất.',
            default => 'Yêu cầu hoàn tiền đã được cập nhật.',
        };
    }
}
