<?php

namespace App\Modules\AdminConsole\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class OrganizerApprovedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $organizerName,
        public readonly string $organizationName,
        public readonly string $loginUrl,
        public readonly string $appName,
        public readonly string $supportEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->appName} organizer account has been approved",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.organizer-approved',
        );
    }
}
