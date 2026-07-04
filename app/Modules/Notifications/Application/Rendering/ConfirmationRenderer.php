<?php

namespace App\Modules\Notifications\Application\Rendering;

use Illuminate\Contracts\View\Factory as ViewFactory;

final readonly class ConfirmationRenderer
{
    public function __construct(private ViewFactory $views) {}

    /** @param array{event_name:string,order_reference:string,credential_url:string} $data
     * @return array{subject:string,body:string}
     */
    public function render(string $locale, array $data): array
    {
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'en';

        return [
            'subject' => trans('phase1.confirmation_subject', ['event' => $data['event_name']], $locale),
            'body' => $this->views->make('mail.phase1.registration-confirmation', [
                ...$data,
                'locale' => $locale,
                'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            ])->render(),
        ];
    }
}
