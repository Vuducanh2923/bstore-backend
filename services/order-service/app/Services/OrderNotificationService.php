<?php

namespace App\Services;

use App\Mail\OrderNotificationMail;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OrderNotificationService
{
    public function __construct(private readonly CustomerEmailClient $customers) {}

    public function sendCreated(Order $order): void
    {
        $this->create(
            userId: (int) $order->user_id,
            orderId: (int) $order->id,
            type: 'order_created',
            message: "Don hang {$order->order_code} da duoc tao thanh cong.",
        );

        $this->send($order, 'created');
    }

    public function sendStatusUpdated(Order $order): void
    {
        $this->create(
            userId: (int) $order->user_id,
            orderId: (int) $order->id,
            type: 'order_status_updated',
            message: $this->statusMessage($order),
            data: ['status' => $order->status],
        );

        $this->send($order, 'status_updated');
    }

    public function create(
        ?int $userId,
        ?int $orderId,
        string $type,
        string $message,
        ?string $title = null,
        array $data = [],
    ): void {
        if (! $this->notificationsTableExists()) {
            return;
        }

        try {
            Notification::create([
                'user_id' => $userId,
                'order_id' => $orderId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data ?: null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Could not create order notification.', [
                'user_id' => $userId,
                'order_id' => $orderId,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function send(Order $order, string $eventType): void
    {
        $order = $order->fresh(['items']) ?? $order->loadMissing('items');
        $recipient = $this->recipientEmail($order);

        if (! $recipient) {
            Log::info('Skipped order notification email because recipient email is missing.', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
            ]);

            return;
        }

        try {
            Mail::to($recipient)->queue(new OrderNotificationMail($this->orderPayload($order), $eventType));
        } catch (Throwable $exception) {
            Log::error('Could not send order notification email.', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function recipientEmail(Order $order): ?string
    {
        if (filter_var($order->receiver_email, FILTER_VALIDATE_EMAIL)) {
            return (string) $order->receiver_email;
        }

        return $this->customers->emailForUser((int) $order->user_id);
    }

    private function orderPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_code' => $order->order_code,
            'status' => $order->status,
            'status_label' => $order->statusLabel(),
            'total_amount' => $order->final_amount,
            'created_at' => $order->created_at,
            'items' => $order->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'color' => $item->color,
                'ram' => $item->ram,
                'storage' => $item->storage,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
            ])->values()->all(),
        ];
    }

    private function statusMessage(Order $order): string
    {
        if ($order->cancel_request_status === Order::CANCEL_REQUEST_PENDING) {
            return 'Yeu cau huy don dang cho xu ly.';
        }

        return match ($order->status) {
            Order::STATUS_PROCESSING => "Don hang da duoc nhan vien {$order->assigned_staff_name} tiep nhan.",
            Order::STATUS_SHIPPING => 'Don hang dang duoc van chuyen.',
            Order::STATUS_DELIVERED => 'Don hang da giao thanh cong.',
            Order::STATUS_COMPLETED => 'Don hang da hoan tat.',
            Order::STATUS_CANCELLED => 'Yeu cau huy don da duoc chap nhan.',
            default => "Don hang da duoc cap nhat sang trang thai {$order->status}.",
        };
    }

    private function notificationsTableExists(): bool
    {
        try {
            return Schema::connection('bstore_order')->hasTable('notifications');
        } catch (Throwable) {
            return false;
        }
    }
}
