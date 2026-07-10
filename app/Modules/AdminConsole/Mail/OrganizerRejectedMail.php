<?php

namespace App\Modules\AdminConsole\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class OrganizerRejectedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $organizerName,
        public readonly string $organizationName,
        public readonly string $reason,
        public readonly string $appName,
        public readonly string $supportEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Update on your {$this->appName} organizer registration request",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.organizer-rejected',
        );
    }
}
