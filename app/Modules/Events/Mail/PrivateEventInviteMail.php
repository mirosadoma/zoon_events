<?php

namespace App\Modules\Events\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrivateEventInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $eventName,
        public readonly string $inviteUrl,
        public readonly string $preferredLocale = 'en',
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->preferredLocale === 'ar'
            ? "دعوتك للتسجيل في {$this->eventName}"
            : "You're invited to register for {$this->eventName}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.private-event-invite',
            with: [
                'eventName' => $this->eventName,
                'inviteUrl' => $this->inviteUrl,
                'locale' => $this->preferredLocale,
            ],
        );
    }
}
