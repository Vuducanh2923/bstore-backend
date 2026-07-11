<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Order;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComplaintService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly UserDirectoryService $users,
        private readonly OrderHistoryService $histories,
        private readonly OrderNotificationService $notifications,
    ) {}

    public function paginated(array $filters, array $actor): LengthAwarePaginator
    {
        $actor = $this->users->actor($actor);
        $query = Complaint::with('order');

        if ($actor['role'] === 'CUSTOMER') {
            $query->where('customer_id', $actor['id']);
        } elseif (! in_array($actor['role'], ['ADMIN', 'STAFF'], true)) {
            throw new AuthorizationException('Khong co quyen xem khieu nai');
        }

        if (! empty($filters['status'])) {
            $query->where('status', strtolower((string) $filters['status']));
        }

        $perPage = min(self::MAX_PER_PAGE, max(1, (int) ($filters['limit'] ?? $filters['per_page'] ?? self::DEFAULT_PER_PAGE)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(int $complaintId, array $actor): ?Complaint
    {
        $complaint = Complaint::with('order')->find($complaintId);

        if (! $complaint) {
            return null;
        }

        $this->ensureCanView($complaint, $this->users->actor($actor));

        return $complaint;
    }

    public function create(array $data, array $actor): Complaint
    {
        $actor = $this->users->actor($actor);

        if ($actor['role'] !== 'CUSTOMER') {
            throw new AuthorizationException('Chi khach hang moi duoc gui khieu nai');
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

            $assignedStaffId = (int) ($order->getAttribute('assigned_staff_id') ?? 0);
            $staffProfile = $assignedStaffId > 0 ? $this->users->profile($assignedStaffId) : [];

            $complaint = Complaint::create([
                'order_id' => $order->id,
                'customer_id' => $actor['id'],
                'staff_id' => $assignedStaffId ?: null,
                'staff_name' => $order->getAttribute('assigned_staff_name') ?: ($staffProfile['name'] ?? null),
                'staff_phone' => $staffProfile['phone'] ?? null,
                'title' => $data['title'],
                'content' => $data['content'],
                'status' => Complaint::STATUS_PENDING,
            ]);

            $this->histories->record(
                (int) $order->id,
                'complaint_created',
                (string) $order->status,
                (string) $order->status,
                null,
                $complaint->title,
            );

            $this->notifications->create(
                userId: (int) $order->user_id,
                orderId: (int) $order->id,
                type: 'complaint_created',
                message: 'Khieu nai da duoc gui.',
                data: ['complaint_id' => $complaint->id],
            );

            return $complaint->fresh('order') ?? $complaint;
        });
    }

    public function process(int $complaintId, array $actor, ?string $reply = null): ?Complaint
    {
        return $this->transition($complaintId, $actor, Complaint::STATUS_PROCESSING, $reply);
    }

    public function resolve(int $complaintId, array $actor, ?string $reply = null): ?Complaint
    {
        return $this->transition($complaintId, $actor, Complaint::STATUS_RESOLVED, $reply);
    }

    public function reject(int $complaintId, array $actor, ?string $reply = null): ?Complaint
    {
        return $this->transition($complaintId, $actor, Complaint::STATUS_REJECTED, $reply);
    }

    public function serialize(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'order_id' => $complaint->order_id,
            'customer_id' => $complaint->customer_id,
            'staff_id' => $complaint->staff_id,
            'assigned_staff_name' => $complaint->staff_name,
            'assigned_staff_phone' => $complaint->staff_phone,
            'title' => $complaint->title,
            'content' => $complaint->content,
            'status' => $complaint->status,
            'reply' => $complaint->reply,
            'handled_at' => $complaint->handled_at,
            'created_at' => $complaint->created_at,
            'updated_at' => $complaint->updated_at,
        ];
    }

    public function serializeMany(iterable $complaints): array
    {
        return collect($complaints)
            ->map(fn (Complaint $complaint) => $this->serialize($complaint))
            ->values()
            ->all();
    }

    private function transition(int $complaintId, array $actor, string $nextStatus, ?string $reply): ?Complaint
    {
        $complaint = DB::connection('bstore_order')->transaction(function () use ($complaintId, $actor, $nextStatus, $reply) {
            $complaint = Complaint::with('order')->lockForUpdate()->find($complaintId);

            if (! $complaint) {
                return null;
            }

            $actor = $this->users->actor($actor);
            $this->ensureCanHandle($complaint, $actor);
            $this->ensureComplaintTransition($complaint->status, $nextStatus);

            $oldStatus = $complaint->status;
            $complaint->status = $nextStatus;

            if ($reply !== null) {
                $complaint->reply = $reply;
            }

            if (in_array($nextStatus, [Complaint::STATUS_RESOLVED, Complaint::STATUS_REJECTED], true)) {
                $complaint->handled_at = now();
            }

            $complaint->save();

            $this->histories->record(
                (int) $complaint->order_id,
                'complaint_'.$nextStatus,
                (string) $complaint->order?->status,
                (string) $complaint->order?->status,
                $actor,
                $reply,
            );

            return $complaint->fresh('order') ?? $complaint;
        });

        if ($complaint) {
            $this->notifications->create(
                userId: (int) $complaint->customer_id,
                orderId: (int) $complaint->order_id,
                type: 'complaint_'.$complaint->status,
                message: $this->messageForStatus($complaint->status),
                data: ['complaint_id' => $complaint->id],
            );
        }

        return $complaint;
    }

    private function ensureComplaintTransition(string $currentStatus, string $nextStatus): void
    {
        $allowed = [
            Complaint::STATUS_PENDING => [
                Complaint::STATUS_PROCESSING,
                Complaint::STATUS_RESOLVED,
                Complaint::STATUS_REJECTED,
            ],
            Complaint::STATUS_PROCESSING => [
                Complaint::STATUS_RESOLVED,
                Complaint::STATUS_REJECTED,
            ],
        ];

        if (! in_array($nextStatus, $allowed[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ['Trang thai khieu nai khong hop le'],
            ]);
        }
    }

    private function ensureCanView(Complaint $complaint, array $actor): void
    {
        if ($actor['role'] === 'CUSTOMER' && (int) $complaint->customer_id !== (int) $actor['id']) {
            throw new AuthorizationException('Khong co quyen xem khieu nai nay');
        }

        if (! in_array($actor['role'], ['CUSTOMER', 'ADMIN', 'STAFF'], true)) {
            throw new AuthorizationException('Khong co quyen xem khieu nai');
        }
    }

    private function ensureCanHandle(Complaint $complaint, array $actor): void
    {
        if ($actor['role'] === 'ADMIN') {
            return;
        }

        if ($actor['role'] !== 'STAFF') {
            throw new AuthorizationException('Khong co quyen xu ly khieu nai');
        }

        if ((int) ($complaint->staff_id ?? 0) !== (int) $actor['id']) {
            throw new AuthorizationException('Chi nhan vien phu trach don hang moi duoc xu ly khieu nai');
        }
    }

    private function messageForStatus(string $status): string
    {
        return match ($status) {
            Complaint::STATUS_PROCESSING => 'Khieu nai dang duoc xu ly.',
            Complaint::STATUS_RESOLVED => 'Khieu nai da duoc giai quyet.',
            Complaint::STATUS_REJECTED => 'Khieu nai da bi tu choi.',
            default => 'Khieu nai da duoc cap nhat.',
        };
    }
}
