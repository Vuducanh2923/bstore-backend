<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegisterOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(
        public readonly string $otpCode,
        public readonly int $expiresInMinutes = 5,
    ) {}

    // Thực hiện envelope.
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Mã xác thực đăng ký BStore',
        );
    }

    // Thực hiện content.
    public function content(): Content
    {
        return new Content(
            view: 'emails.register-otp',
        );
    }

    // Thực hiện attachments.
    public function attachments(): array
    {
        return [];
    }
}
