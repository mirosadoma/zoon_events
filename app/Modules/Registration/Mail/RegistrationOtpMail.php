<?php

namespace App\Modules\Registration\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $eventName,
        public readonly string $preferredLocale = 'en',
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->preferredLocale === 'ar'
            ? "رمز التحقق لتسجيل {$this->eventName}"
            : "Your verification code for {$this->eventName}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $direction = $this->preferredLocale === 'ar' ? 'rtl' : 'ltr';

        return new Content(
            html: 'emails.registration-otp',
            with: [
                'code' => $this->code,
                'eventName' => $this->eventName,
                'locale' => $this->preferredLocale,
                'direction' => $direction,
                'textAlign' => $direction === 'rtl' ? 'right' : 'left',
                'appName' => config('zonetec.name', 'Zonetec'),
            ],
        );
    }
}
