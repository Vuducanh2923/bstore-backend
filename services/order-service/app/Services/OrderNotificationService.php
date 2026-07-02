<?php

namespace App\Services;

use App\Mail\OrderNotificationMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OrderNotificationService
{
    public function __construct(private readonly CustomerEmailClient $customers) {}

    public function sendCreated(Order $order): void
    {
        $this->send($order, 'created');
    }

    public function sendStatusUpdated(Order $order): void
    {
        $this->send($order, 'status_updated');
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
            Mail::to($recipient)->send(new OrderNotificationMail($this->orderPayload($order), $eventType));
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
}
