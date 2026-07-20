<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\BadgePrinting\Application\Actions\BuildBadgePrintDocumentAction;
use App\Modules\BadgePrinting\Application\Actions\RenderBadgeEmailHtmlAction;
use App\Modules\BadgePrinting\Application\Actions\RenderBadgePngAction;
use App\Modules\BadgePrinting\Application\Support\PrepareBadgeEmailEmbeddedImages;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Mail\RegistrationBadgeMail;
use App\Modules\Notifications\Application\Rendering\QrCodeImageDataUri;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final readonly class SendRegistrationBadgeEmail
{
    public const QR_CONTENT_ID = 'badge-qr';

    public const BADGE_PNG_CONTENT_ID = 'badge-preview';

    public function __construct(
        private BuildBadgePrintDocumentAction $printDocuments,
        private RenderBadgePngAction $badgePng,
        private RenderBadgeEmailHtmlAction $badgeHtml,
        private PrepareBadgeEmailEmbeddedImages $embeddedImages,
        private QrCodeImageDataUri $qrImages,
    ) {}

    public function execute(
        Event $event,
        string $attendeeId,
        string $credentialId,
        string $email,
        string $locale = 'en',
    ): void {
        $template = BadgeTemplate::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if ($template === null) {
            $template = BadgeTemplate::query()
                ->where('tenant_id', $event->tenant_id)
                ->where('event_id', $event->id)
                ->orderByDesc('id')
                ->first();
        }

        if ($template === null) {
            Log::info('public_registration.badge_mail_skipped_no_template', [
                'event_id' => $event->id,
                'attendee_id' => $attendeeId,
            ]);

            return;
        }

        $document = $this->printDocuments->build(
            (string) $event->tenant_id,
            (string) $event->id,
            $attendeeId,
            $credentialId,
            $template,
            [],
            false,
        );

        $fields = $document['fields'];
        $qrPayload = is_string($fields['qr'] ?? null) ? $fields['qr'] : null;
        $qrPngBytes = $qrPayload !== null ? $this->qrImages->pngBytesFromPayload($qrPayload, 360) : null;

        $badgePngBytes = $this->badgePng->execute($template, $fields, $qrPayload);
        $badgeHtml = null;
        $inlineImages = [];

        if ($badgePngBytes === null) {
            Log::warning('public_registration.badge_png_fallback_html', [
                'event_id' => $event->id,
                'attendee_id' => $attendeeId,
            ]);

            $prepared = $this->embeddedImages->execute($template, $fields);
            $fields = $prepared['fields'];
            $template = $prepared['template'];
            $inlineImages = $prepared['images'];
            $qrCidSrc = $qrPngBytes !== null ? 'cid:'.self::QR_CONTENT_ID : null;
            $badgeHtml = $this->badgeHtml->execute($template, $fields, $qrCidSrc);
        }

        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $eventName = $resolvedLocale === 'ar'
            ? ($event->name_ar ?: $event->name_en)
            : $event->name_en;

        Mail::to($email)->send(new RegistrationBadgeMail(
            eventName: $eventName,
            badge: [
                'attendee_name' => $fields['attendee_name'] ?? null,
                'company' => $fields['company'] ?? null,
                'job_title' => $fields['job_title'] ?? null,
                'ticket_type' => isset($fields['ticket_type']) ? (string) $fields['ticket_type'] : null,
                'tier' => $fields['tier'] ?? null,
                'zone' => $fields['zone'] ?? null,
                'qr' => $qrPayload,
                'template_name' => $template->name,
                'paper_size' => $template->paper_size,
                'background_color' => $template->background_color ?: '#ffffff',
                'html' => $badgeHtml,
                'fields' => $fields,
                'canvas_width' => $template->canvas_width,
                'canvas_height' => $template->canvas_height,
            ],
            preferredLocale: $resolvedLocale,
            qrPngBytes: $badgePngBytes === null ? $qrPngBytes : null,
            qrContentId: self::QR_CONTENT_ID,
            inlineImages: $inlineImages,
            badgePngBytes: $badgePngBytes,
            badgePngContentId: self::BADGE_PNG_CONTENT_ID,
        ));
    }
}
