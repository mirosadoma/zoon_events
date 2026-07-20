<?php

namespace App\Modules\Identity\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $preferredLocale = 'en',
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->preferredLocale === 'ar'
            ? 'رمز استعادة كلمة المرور'
            : 'Password reset verification code';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.password-reset-otp',
            with: [
                'code' => $this->code,
                'locale' => $this->preferredLocale,
            ],
        );
    }
}
