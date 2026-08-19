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

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly CustomerEmailClient $customers) {}

    // Gửi hoặc phát created.
    public function sendCreated(Order $order): void
    {
        $this->create(
            userId: (int) $order->user_id,
            orderId: (int) $order->id,
            type: 'order_created',
            message: "Đơn hàng {$order->order_code} đã được tạo thành công.",
        );

        $this->send($order, 'created');
    }

    // Gửi hoặc phát trạng thái updated.
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

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
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

    // Gửi hoặc phát dữ liệu theo nghiệp vụ của hàm.
    private function send(Order $order, string $eventType): void
    {
        $order = $order->fresh(['items']) ?? $order->loadMissing('items');
        $payload = $this->orderPayload($order);
        $explicitRecipient = filter_var($order->receiver_email, FILTER_VALIDATE_EMAIL)
            ? (string) $order->receiver_email
            : null;

        // SMTP với queue sync sẽ chặn response tạo đơn; defer cả lookup email và gửi mail.
        if ((string) config('queue.default') === 'sync') {
            dispatch(function () use ($eventType, $explicitRecipient, $order, $payload): void {
                $recipient = $explicitRecipient ?: $this->customers->emailForUser((int) $order->user_id);
                $this->sendEmail($recipient, $order, $eventType, $payload);
            })->afterResponse();

            return;
        }

        $recipient = $explicitRecipient ?: $this->recipientEmail($order);
        $this->sendEmail($recipient, $order, $eventType, $payload, true);
    }

    // Gửi email xác nhận hoặc cập nhật đơn hàng.
    private function sendEmail(
        ?string $recipient,
        Order $order,
        string $eventType,
        array $payload,
        bool $queue = false,
    ): void {
        if (! $recipient) {
            Log::info('Skipped order notification email because recipient email is missing.', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
            ]);

            return;
        }

        try {
            $mailable = new OrderNotificationMail($payload, $eventType);

            if ($queue) {
                Mail::to($recipient)->queue($mailable);
            } else {
                Mail::to($recipient)->send($mailable);
            }
        } catch (Throwable $exception) {
            Log::error('Could not send order notification email.', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    // Thực hiện recipient email.
    private function recipientEmail(Order $order): ?string
    {
        if (filter_var($order->receiver_email, FILTER_VALIDATE_EMAIL)) {
            return (string) $order->receiver_email;
        }

        return $this->customers->emailForUser((int) $order->user_id);
    }

    // Thực hiện đơn hàng dữ liệu gửi.
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

    // Thực hiện trạng thái thông báo.
    private function statusMessage(Order $order): string
    {
        if ($order->cancel_request_status === Order::CANCEL_REQUEST_PENDING) {
            return 'Yêu cầu hủy đơn đang chờ xử lý.';
        }

        return match ($order->status) {
            Order::STATUS_PROCESSING => "Đơn hàng đã được nhân viên {$order->assigned_staff_name} tiếp nhận.",
            Order::STATUS_SHIPPING => 'Đơn hàng đang được vận chuyển.',
            Order::STATUS_DELIVERED => 'Đơn hàng đã giao thành công.',
            Order::STATUS_COMPLETED => 'Đơn hàng đã hoàn tất.',
            Order::STATUS_CANCELLED => 'Yêu cầu hủy đơn đã được chap nhan.',
            default => "Đơn hàng đã được cập nhật sang trạng thái {$order->status}.",
        };
    }

    // Thực hiện notifications bảng tồn tại.
    private function notificationsTableExists(): bool
    {
        try {
            return Schema::connection('bstore_order')->hasTable('notifications');
        } catch (Throwable) {
            return false;
        }
    }
}
