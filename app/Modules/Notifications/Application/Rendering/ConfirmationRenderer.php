<?php

namespace App\Modules\Notifications\Application\Rendering;

use App\Modules\Notifications\Domain\NotificationEmbeddedImage;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\URL;

final readonly class ConfirmationRenderer
{
    public function __construct(
        private ViewFactory $views,
        private QrCodeImageDataUri $qrImages,
    ) {}

    /** @param array{event_name:string,order_reference:string,credential_url:string,qr_payload?:string,attendee_name?:string,app_name?:string,support_email?:string} $data
     * @return array{subject:string,body:string,embedded_images:list<NotificationEmbeddedImage>}
     */
    public function render(string $locale, array $data): array
    {
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'en';
        $qrPayload = $data['qr_payload'] ?? '';
        $direction = $locale === 'ar' ? 'rtl' : 'ltr';
        $embeddedImages = [];
        $qrImageSrc = null;

        $qrBytes = $this->qrImages->pngBytesFromPayload($qrPayload);
        if ($qrBytes !== null) {
            $contentId = 'qr-code';
            $qrImageSrc = 'cid:'.$contentId;
            $embeddedImages[] = new NotificationEmbeddedImage($contentId, 'image/png', $qrBytes);
        }

        return [
            'subject' => trans('phase1.confirmation_subject', ['event' => $data['event_name']], $locale),
            'body' => $this->views->make('mail.phase1.registration-confirmation', [
                ...$data,
                'qr_payload' => $qrPayload,
                'qr_image_src' => $qrImageSrc,
                'attendee_name' => $this->normalizeAttendeeName($data['attendee_name'] ?? ''),
                'app_name' => $data['app_name'] ?? config('zonetec.name', 'Zonetec'),
                'support_email' => $data['support_email'] ?? (string) config('mail.from.address'),
                'unsubscribe_url' => URL::route('public.notifications.unsubscribe', ['locale' => $locale]),
                'locale' => $locale,
                'direction' => $direction,
                'text_align' => $direction === 'rtl' ? 'right' : 'left',
            ])->render(),
            'embedded_images' => $embeddedImages,
        ];
    }

    private function normalizeAttendeeName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }

        if (in_array(strtolower($trimmed), ['guest', 'participant', 'attendee'], true)) {
            return '';
        }

        return $trimmed;
    }
}
