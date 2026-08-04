<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Events\Application\Support\ResolveEventEmailTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventEmailTemplate;
use App\Modules\Events\Mail\CustomEventEmailMail;
use App\Modules\Notifications\Application\Rendering\QrCodeImageDataUri;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the organizer confirmation email template when configured for the event.
 */
final readonly class SendCustomConfirmationEmail
{
    public const QR_CONTENT_ID = 'confirmation-qr';

    public function __construct(
        private ResolveEventEmailTemplate $emailTemplates,
        private QrCodeImageDataUri $qrImages,
    ) {}

    public function hasTemplate(Event $event): bool
    {
        return EventEmailTemplate::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('type', ResolveEventEmailTemplate::TYPE_CONFIRMATION)
            ->exists();
    }

    public function execute(
        Event $event,
        string $email,
        string $locale,
        string $attendeeName = '',
        string $phone = '',
        string $entryCardUrl = '',
        string $qrPayload = '',
    ): bool {
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $eventName = $resolvedLocale === 'ar'
            ? ($event->name_ar ?: $event->name_en)
            : $event->name_en;

        $qrPngBytes = $qrPayload !== ''
            ? $this->qrImages->pngBytesFromPayload($qrPayload, 360)
            : null;

        $qrPlaceholder = $qrPngBytes !== null
            ? '<img src="cid:'.self::QR_CONTENT_ID.'" alt="QR Code" width="200" height="200" style="display:block;width:200px;height:200px;max-width:200px;border:0;" />'
            : '';

        if ($qrPlaceholder !== '' && $entryCardUrl !== '') {
            $label = e(__('phase1.view_credential', [], $resolvedLocale));
            $qrPlaceholder .= '<div>'
                .'<a href="'.e($entryCardUrl).'" '
                .'style="display:inline-block;color:#15c;text-decoration:none;border-radius:999px;font-size:15px;font-weight:600;line-height:1.2;">'
                .$label
                .'</a>'
                .'</div>';
        }

        $custom = $this->emailTemplates->render(
            $event,
            ResolveEventEmailTemplate::TYPE_CONFIRMATION,
            $resolvedLocale,
            [
                'user_name' => $attendeeName,
                'user_email' => $email,
                'user_phone' => $phone,
                'event_name' => $eventName,
                'qr_code' => $qrPlaceholder,
                'entry_card_url' => $entryCardUrl,
            ],
        );

        if ($custom === null) {
            return false;
        }

        $extraImages = [];
        if ($qrPngBytes !== null) {
            $extraImages[] = [
                'cid' => self::QR_CONTENT_ID,
                'bytes' => $qrPngBytes,
                'mime' => 'image/png',
                'filename' => 'confirmation-qr.png',
            ];
        }

        Mail::to($email)->send(new CustomEventEmailMail(
            $custom['subject'],
            $custom['html'],
            $resolvedLocale,
            $extraImages,
            $qrPngBytes,
            'event-qr.png',
        ));

        return true;
    }
}
