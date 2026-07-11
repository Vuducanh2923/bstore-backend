<?php

namespace App\Services;

use App\Models\OrderHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OrderHistoryService
{
    public function record(
        int $orderId,
        string $action,
        ?string $oldStatus,
        ?string $newStatus,
        ?array $actor = null,
        ?string $note = null,
    ): void {
        if (! $this->tableExists()) {
            return;
        }

        try {
            OrderHistory::create([
                'order_id' => $orderId,
                'action' => $action,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'staff_id' => $actor['id'] ?? null,
                'staff_name' => $actor['name'] ?? null,
                'note' => $note,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Could not create order history.', [
                'order_id' => $orderId,
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function previousStatusBeforeCancel(int $orderId): ?string
    {
        if (! $this->tableExists()) {
            return null;
        }

        return OrderHistory::query()
            ->where('order_id', $orderId)
            ->where('action', 'cancel_requested')
            ->latest('id')
            ->value('old_status');
    }

    private function tableExists(): bool
    {
        try {
            return Schema::connection('bstore_order')->hasTable('order_histories');
        } catch (Throwable) {
            return false;
        }
    }
}
