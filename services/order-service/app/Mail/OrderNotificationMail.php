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

    public function __construct(
        public readonly array $order,
        public readonly string $eventType = 'created',
    ) {}

    public function envelope(): Envelope
    {
        $orderCode = $this->order['order_code'] ?? '';
        $subject = $this->eventType === 'status_updated'
            ? "BStore cap nhat don hang {$orderCode}"
            : "BStore xac nhan don hang {$orderCode}";

        return new Envelope(subject: trim($subject));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
