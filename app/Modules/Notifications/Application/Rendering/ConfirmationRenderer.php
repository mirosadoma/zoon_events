<?php

namespace App\Modules\Notifications\Application\Rendering;

use Illuminate\Contracts\View\Factory as ViewFactory;

final readonly class ConfirmationRenderer
{
    public function __construct(private ViewFactory $views) {}

    /** @param array{event_name:string,order_reference:string,credential_url:string,qr_payload?:string,attendee_name?:string,app_name?:string,support_email?:string} $data
     * @return array{subject:string,body:string}
     */
    public function render(string $locale, array $data): array
    {
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'en';

        return [
            'subject' => trans('phase1.confirmation_subject', ['event' => $data['event_name']], $locale),
            'body' => $this->views->make('mail.phase1.registration-confirmation', [
                ...$data,
                'qr_payload' => $data['qr_payload'] ?? '',
                'attendee_name' => $data['attendee_name'] ?? 'Participant',
                'app_name' => $data['app_name'] ?? config('zonetec.name', 'Zonetec'),
                'support_email' => $data['support_email'] ?? (string) config('mail.from.address'),
                'locale' => $locale,
                'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            ])->render(),
        ];
    }
}
