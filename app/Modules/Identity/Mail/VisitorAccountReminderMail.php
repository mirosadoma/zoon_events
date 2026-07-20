<?php

namespace App\Modules\Identity\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VisitorAccountReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly string $loginUrl,
        public readonly string $preferredLocale = 'en',
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->preferredLocale === 'ar'
            ? 'حسابك موجود — سجّل الدخول'
            : 'Your account is ready — sign in';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $direction = $this->preferredLocale === 'ar' ? 'rtl' : 'ltr';

        return new Content(
            html: 'emails.visitor-account-reminder',
            with: [
                'email' => $this->email,
                'loginUrl' => $this->loginUrl,
                'locale' => $this->preferredLocale,
                'direction' => $direction,
                'textAlign' => $direction === 'rtl' ? 'right' : 'left',
                'appName' => config('zonetec.name', 'Zonetec'),
            ],
        );
    }
}
