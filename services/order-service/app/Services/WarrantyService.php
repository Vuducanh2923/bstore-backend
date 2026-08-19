<?php

namespace App\Services;

use App\Exceptions\WarrantyConflictException;
use App\Models\Order;
use App\Models\WarrantyRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class WarrantyService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly UserDirectoryService $users) {}

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
    public function create(int $customerId, array $data): WarrantyRequest
    {
        return DB::connection('bstore_order')->transaction(function () use ($customerId, $data): WarrantyRequest {
            $order = Order::with('items')->lockForUpdate()->find((int) $data['order_id']);

            if (! $order) {
                throw ValidationException::withMessages(['order_id' => ['Đơn hàng không tồn tại']]);
            }
            if ((int) $order->user_id !== $customerId) {
                throw new AuthorizationException('Đơn hàng không thuộc khách hàng');
            }
            if (! in_array(strtolower((string) $order->status), [
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ], true)) {
                throw ValidationException::withMessages(['order_id' => ['Don hang chua duoc giao thanh cong']]);
            }

            $item = $order->items->firstWhere('id', (int) $data['order_item_id']);
            if (! $item) {
                throw ValidationException::withMessages(['order_item_id' => ['Sản phẩm không thuộc đơn hàng']]);
            }

            $policy = $this->warrantyPolicy((int) $item->product_id, (int) $item->product_variant_id);
            if (! $policy || (int) $policy->duration_months <= 0 || ! (bool) $policy->repair_support) {
                throw ValidationException::withMessages(['order_item_id' => ['Sản phẩm không có chính sách bảo hành']]);
            }

            $start = Carbon::parse($order->delivered_at ?: $order->updated_at ?: $order->created_at)->startOfDay();
            $end = $start->copy()->addMonthsNoOverflow((int) $policy->duration_months);
            if (today()->gt($end)) {
                throw ValidationException::withMessages(['order_item_id' => ['Sản phẩm đã hết hạn bảo hành']]);
            }

            $duplicate = WarrantyRequest::query()
                ->where('user_id', $customerId)
                ->where('order_item_id', $item->id)
                ->whereIn('status', WarrantyRequest::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw new WarrantyConflictException('Sản phẩm đã có yêu cầu bảo hành đang xử lý');
            }

            $warranty = WarrantyRequest::create([
                'user_id' => $customerId,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'type' => 'repair',
                'reason' => trim($data['reason']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'status' => WarrantyRequest::STATUS_PENDING,
                'warranty_start_date' => $start->toDateString(),
                'warranty_end_date' => $end->toDateString(),
            ]);
            $warranty->request_code = sprintf('WR-%s-%06d', now()->format('Ymd'), $warranty->id);
            $warranty->save();

            return $this->hydrate($warranty->fresh(['order', 'orderItem']) ?? $warranty);
        });
    }

    // Thực hiện khách hàng danh sách.
    public function customerList(int $customerId, array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->where('user_id', $customerId)
            ->paginate($this->perPage($filters));
    }

    // Thực hiện quản trị danh sách.
    public function adminList(array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($filters, true)->paginate($this->perPage($filters));
    }

    // Thực hiện khách hàng chi tiết.
    public function customerDetail(int $customerId, int $id): ?WarrantyRequest
    {
        $warranty = WarrantyRequest::with(['order', 'orderItem'])->find($id);
        if (! $warranty) {
            return null;
        }
        if ((int) $warranty->user_id !== $customerId) {
            throw new AuthorizationException('Không được xem yêu cầu bảo hành của khách hàng khác');
        }

        return $this->hydrate($warranty, true);
    }

    // Thực hiện quản trị chi tiết.
    public function adminDetail(int $id): ?WarrantyRequest
    {
        $warranty = WarrantyRequest::with(['order', 'orderItem'])->find($id);

        return $warranty ? $this->hydrate($warranty, true) : null;
    }

    // Xóa hoặc hủy dữ liệu theo nghiệp vụ của hàm.
    public function cancel(int $customerId, int $id): ?WarrantyRequest
    {
        return $this->transition($id, WarrantyRequest::STATUS_PENDING, WarrantyRequest::STATUS_CANCELLED, null, $customerId);
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function approve(int $id, array $actor, ?string $note): ?WarrantyRequest
    {
        return DB::connection('bstore_order')->transaction(function () use ($id, $actor, $note) {
            $warranty = WarrantyRequest::with(['order', 'orderItem'])->lockForUpdate()->find($id);
            if (! $warranty) {
                return null;
            }
            $this->assertStatus($warranty, WarrantyRequest::STATUS_PENDING);
            $this->assertStillEligible($warranty);
            $warranty->update([
                'status' => WarrantyRequest::STATUS_APPROVED,
                'approved_by' => (int) $actor['id'],
                'approved_at' => now(),
                'processing_note' => $note,
            ]);

            return $this->hydrate($warranty->fresh(['order', 'orderItem']) ?? $warranty, true);
        });
    }

    // Cập nhật dữ liệu theo nghiệp vụ của hàm.
    public function reject(int $id, array $actor, string $reason): ?WarrantyRequest
    {
        return DB::connection('bstore_order')->transaction(function () use ($id, $actor, $reason) {
            $warranty = WarrantyRequest::with(['order', 'orderItem'])->lockForUpdate()->find($id);
            if (! $warranty) {
                return null;
            }
            $this->assertStatus($warranty, WarrantyRequest::STATUS_PENDING);
            $warranty->update([
                'status' => WarrantyRequest::STATUS_REJECTED,
                'rejection_reason' => trim($reason),
                'rejected_by' => (int) $actor['id'],
                'rejected_at' => now(),
            ]);

            return $this->hydrate($warranty->fresh(['order', 'orderItem']) ?? $warranty, true);
        });
    }

    // Thực hiện đang xử lý.
    public function processing(int $id, ?string $note): ?WarrantyRequest
    {
        return $this->transition($id, WarrantyRequest::STATUS_APPROVED, WarrantyRequest::STATUS_PROCESSING, $note);
    }

    // Thực hiện complete.
    public function complete(int $id, ?string $note): ?WarrantyRequest
    {
        return $this->transition($id, WarrantyRequest::STATUS_PROCESSING, WarrantyRequest::STATUS_COMPLETED, $note);
    }

    // Thực hiện hydrate.
    public function hydrate(WarrantyRequest $warranty, bool $withCustomer = false): WarrantyRequest
    {
        $item = $warranty->orderItem;
        $policy = $item ? $this->warrantyPolicy((int) $item->product_id, (int) $item->product_variant_id) : null;
        if ($item) {
            $warranty->setAttribute('product_context', [
                'id' => (int) $item->product_id,
                'variant_id' => (int) $item->product_variant_id,
                'name' => $item->product_name,
                'image_url' => $item->product_image,
                'color' => $item->color,
                'ram' => $item->ram,
                'storage' => $item->storage,
            ]);
        }
        $warranty->setAttribute('warranty_policy_context', $policy ? (array) $policy : null);
        if ($withCustomer) {
            $warranty->setAttribute('customer_context', $this->users->profile((int) $warranty->user_id) ?: null);
        }

        return $warranty;
    }

    // Thực hiện filtered query.
    private function filteredQuery(array $filters, bool $admin = false)
    {
        $query = WarrantyRequest::with(['order', 'orderItem']);
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if ($admin && ! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if ($admin && ! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($query) use ($search, $admin): void {
                $query->where('request_code', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($order) use ($search, $admin): void {
                        $order->where('order_code', 'like', "%{$search}%");
                        if ($admin) {
                            $order->orWhere('receiver_name', 'like', "%{$search}%")
                                ->orWhere('receiver_phone', 'like', "%{$search}%");
                        }
                    })
                    ->orWhereHas('orderItem', fn ($item) => $item->where('product_name', 'like', "%{$search}%"));
            });
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    // Thực hiện chuyển trạng thái.
    private function transition(int $id, string $from, string $to, ?string $note = null, ?int $customerId = null): ?WarrantyRequest
    {
        return DB::connection('bstore_order')->transaction(function () use ($id, $from, $to, $note, $customerId) {
            $warranty = WarrantyRequest::with(['order', 'orderItem'])->lockForUpdate()->find($id);
            if (! $warranty) {
                return null;
            }
            if ($customerId !== null && (int) $warranty->user_id !== $customerId) {
                throw new AuthorizationException('Không được cập nhật yêu cầu bảo hành của khách hàng khác');
            }
            $this->assertStatus($warranty, $from);
            $values = ['status' => $to];
            if ($note !== null) {
                $values['processing_note'] = trim($note);
            }
            if ($to === WarrantyRequest::STATUS_COMPLETED) {
                $values['completed_at'] = now();
            }
            $warranty->update($values);

            return $this->hydrate($warranty->fresh(['order', 'orderItem']) ?? $warranty, true);
        });
    }

    // Thực hiện assert trạng thái.
    private function assertStatus(WarrantyRequest $warranty, string $required): void
    {
        if ($warranty->status !== $required) {
            throw new WarrantyConflictException("Chỉ được cập nhật yêu cầu bảo hành đang {$required}");
        }
    }

    // Thực hiện assert still eligible.
    private function assertStillEligible(WarrantyRequest $warranty): void
    {
        if (! $warranty->order || ! $warranty->orderItem || (int) $warranty->orderItem->order_id !== (int) $warranty->order_id) {
            throw ValidationException::withMessages(['order_item_id' => ['Sản phẩm không thuộc đơn hàng']]);
        }
        if (today()->gt(Carbon::parse($warranty->warranty_end_date))) {
            throw ValidationException::withMessages(['warranty_end_date' => ['Sản phẩm đã hết hạn bảo hành']]);
        }
    }

    // Thực hiện bảo hành policy.
    private function warrantyPolicy(int $productId, int $variantId): ?object
    {
        try {
            return DB::connection('bstore_catalog')->table('products')
                ->join('product_variants', 'product_variants.product_id', '=', 'products.id')
                ->join('warranty_policies', 'warranty_policies.id', '=', 'products.warranty_policy_id')
                ->where('products.id', $productId)
                ->where('product_variants.id', $variantId)
                ->where(function ($query): void {
                    $query->whereNull('warranty_policies.status')->orWhere('warranty_policies.status', 'active');
                })
                ->select('warranty_policies.*')
                ->first();
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['product_id' => ['Không thể xác minh chính sách bảo hành từ Catalog']]);
        }
    }

    // Thực hiện per trang.
    private function perPage(array $filters): int
    {
        return min(self::MAX_PER_PAGE, max(1, (int) ($filters['per_page'] ?? $filters['limit'] ?? self::DEFAULT_PER_PAGE)));
    }
}
