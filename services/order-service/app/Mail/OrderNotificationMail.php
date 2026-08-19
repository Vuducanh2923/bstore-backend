<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(
        public readonly array $order,
        public readonly string $eventType = 'created',
    ) {}

    // Thực hiện envelope.
    public function envelope(): Envelope
    {
        $orderCode = $this->order['order_code'] ?? '';
        $subject = $this->eventType === 'status_updated'
            ? "BStore cập nhật đơn hàng {$orderCode}"
            : "BStore xác nhận đơn hàng {$orderCode}";

        return new Envelope(subject: trim($subject));
    }

    // Thực hiện content.
    public function content(): Content
    {
        return new Content(
            view: 'emails.order-notification',
        );
    }

    // Thực hiện attachments.
    public function attachments(): array
    {
        return [];
    }
}
