<?php

namespace App\Modules\Subscriptions\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizerCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $organizationName,
        public readonly string $planName,
        public readonly string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->organizationName} account is ready",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organizer-credentials',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'organizationName' => $this->organizationName,
                'planName' => $this->planName,
                'loginUrl' => $this->loginUrl,
            ],
        );
    }
}
