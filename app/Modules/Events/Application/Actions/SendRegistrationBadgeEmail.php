<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\BadgePrinting\Application\Actions\RenderBadgeEmailHtmlAction;
use App\Modules\BadgePrinting\Application\Actions\RenderBadgePrintPayloadAction;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Mail\RegistrationBadgeMail;
use App\Modules\Notifications\Application\Rendering\QrCodeImageDataUri;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final readonly class SendRegistrationBadgeEmail
{
    public const QR_CONTENT_ID = 'badge-qr';

    public function __construct(
        private RenderBadgePrintPayloadAction $badgePayload,
        private RenderBadgeEmailHtmlAction $badgeHtml,
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

        $payload = $this->badgePayload->execute(
            (string) $event->tenant_id,
            (string) $event->id,
            $attendeeId,
            $credentialId,
            $template,
        );

        $fields = $payload->fields;
        $qrPayload = is_string($fields['qr'] ?? null) ? $fields['qr'] : null;
        $qrPngBytes = $qrPayload !== null ? $this->qrImages->pngBytesFromPayload($qrPayload, 360) : null;
        // CID works in email clients; data-URIs are commonly blocked.
        $qrCidSrc = $qrPngBytes !== null ? 'cid:'.self::QR_CONTENT_ID : null;

        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $eventName = $resolvedLocale === 'ar'
            ? ($event->name_ar ?: $event->name_en)
            : $event->name_en;

        $badgeHtml = $this->badgeHtml->execute($template, $fields, $qrCidSrc);

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
            ],
            preferredLocale: $resolvedLocale,
            qrPngBytes: $qrPngBytes,
            qrContentId: self::QR_CONTENT_ID,
        ));
    }
}
