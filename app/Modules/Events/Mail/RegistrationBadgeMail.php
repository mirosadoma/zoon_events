<?php

namespace App\Modules\Events\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationBadgeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{
     *   attendee_name?:string|null,
     *   company?:string|null,
     *   job_title?:string|null,
     *   ticket_type?:string|null,
     *   tier?:string|null,
     *   zone?:string|null,
     *   qr?:string|null,
     *   template_name?:string|null,
     *   paper_size?:string|null,
     *   background_color?:string|null,
     *   html?:string|null,
     *   fields?:array<string, string|null>,
     *   canvas_width?:int|null,
     *   canvas_height?:int|null
     * }  $badge
     */
    public function __construct(
        public readonly string $eventName,
        public readonly array $badge,
        public readonly string $preferredLocale = 'en',
        public readonly ?string $qrPngBytes = null,
        public readonly string $qrContentId = 'badge-qr',
        /** @var list<array{cid: string, bytes: string, mime: string, filename: string}> */
        public readonly array $inlineImages = [],
        public readonly ?string $badgePngBytes = null,
        public readonly string $badgePngContentId = 'badge-preview',
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->preferredLocale === 'ar'
            ? "شارة الطباعة — {$this->eventName}"
            : "Printable badge — {$this->eventName}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $direction = $this->preferredLocale === 'ar' ? 'rtl' : 'ltr';

        return new Content(
            html: 'emails.registration-badge',
            with: [
                'eventName' => $this->eventName,
                'badge' => $this->badge,
                'badgeHtml' => $this->badge['html'] ?? null,
                'qrPngBytes' => $this->qrPngBytes,
                'qrContentId' => $this->qrContentId,
                'inlineImages' => $this->inlineImages,
                'badgePngBytes' => $this->badgePngBytes,
                'badgePngContentId' => $this->badgePngContentId,
                'locale' => $this->preferredLocale,
                'direction' => $direction,
                'textAlign' => $direction === 'rtl' ? 'right' : 'left',
                'appName' => config('zonetec.name', 'Zonetec'),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->badgePngBytes !== null && $this->badgePngBytes !== '') {
            $attachments[] = Attachment::fromData(fn (): string => $this->badgePngBytes, 'event-badge.png')
                ->withMime('image/png');
        } elseif ($this->qrPngBytes !== null && $this->qrPngBytes !== '') {
            $attachments[] = Attachment::fromData(fn (): string => $this->qrPngBytes, 'event-badge-qr.png')
                ->withMime('image/png');
        }

        return $attachments;
    }
}
