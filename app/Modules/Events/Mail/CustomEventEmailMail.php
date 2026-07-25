<?php

namespace App\Modules\Events\Mail;

use App\Modules\Events\Application\Support\PrepareHtmlEmailEmbeddedImages;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a custom organizer-designed HTML email template.
 */
class CustomEventEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var list<array{cid: string, bytes: string, mime: string, filename: string}> */
    public array $inlineImages = [];

    public string $htmlBody;

    /**
     * @param  list<array{cid: string, bytes: string, mime: string, filename: string}>  $extraInlineImages
     */
    public function __construct(
        public readonly string $emailSubject,
        string $htmlBody,
        public readonly string $preferredLocale = 'en',
        array $extraInlineImages = [],
        public readonly ?string $attachmentPngBytes = null,
        public readonly string $attachmentFilename = 'attachment.png',
    ) {
        $prepared = app(PrepareHtmlEmailEmbeddedImages::class)->execute($htmlBody);
        $this->htmlBody = $prepared['html'];
        $this->inlineImages = array_values([...$prepared['images'], ...$extraInlineImages]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.custom-event',
            with: [
                'htmlBody' => $this->htmlBody,
                'inlineImages' => $this->inlineImages,
                'locale' => $this->preferredLocale,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->attachmentPngBytes === null || $this->attachmentPngBytes === '') {
            return [];
        }

        return [
            Attachment::fromData(
                fn (): string => $this->attachmentPngBytes,
                $this->attachmentFilename,
            )->withMime('image/png'),
        ];
    }
}
