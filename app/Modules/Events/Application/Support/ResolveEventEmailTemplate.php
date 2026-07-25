<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventEmailTemplate;
use Illuminate\Support\Facades\URL;

final class ResolveEventEmailTemplate
{
    public const TYPE_INVITATION = 'invitation';

    public const TYPE_OTP = 'otp';

    public const TYPE_CONFIRMATION = 'confirmation';

    public function __construct(
        private NormalizeEmailTemplateHtml $normalizeHtml,
    ) {}

    /**
     * @param  array<string, string|null>  $placeholders
     * @return array{subject: string, html: string}|null
     */
    public function render(Event $event, string $type, string $locale, array $placeholders): ?array
    {
        $template = EventEmailTemplate::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('type', $type)
            ->first();

        if ($template === null) {
            return null;
        }

        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $isAr = $resolvedLocale === 'ar';
        $subject = $isAr ? ($template->subject_ar ?: $template->subject_en) : ($template->subject_en ?: $template->subject_ar);
        $html = $isAr ? ($template->html_body_ar ?: $template->html_body_en) : ($template->html_body_en ?: $template->html_body_ar);

        if (! is_string($subject) || trim($subject) === '' || ! is_string($html) || trim($html) === '') {
            return null;
        }

        $normalizedHtml = $this->normalizeHtml->execute($html);

        return [
            'subject' => $this->replace($subject, $placeholders),
            'html' => $this->appendUnsubscribeFooter(
                $this->replace($normalizedHtml, $placeholders),
                $resolvedLocale,
            ),
        ];
    }

    /**
     * @param  array<string, string|null>  $placeholders
     */
    private function replace(string $content, array $placeholders): string
    {
        $map = [];
        foreach ($placeholders as $key => $value) {
            $normalized = trim($key, '{} ');
            $map['{{'.$normalized.'}}'] = $value ?? '';
            $map['{'.$normalized.'}'] = $value ?? '';
        }

        return strtr($content, $map);
    }

    public function appendUnsubscribeFooter(string $html, string $locale): string
    {
        if ($html === '' || str_contains($html, 'data-email-unsubscribe="1"')) {
            return $html;
        }

        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $unsubscribeUrl = URL::route('public.notifications.unsubscribe', ['locale' => $resolvedLocale]);
        $here = e(__('phase1.confirmation_footer_here', [], $resolvedLocale));
        $link = '<a href="'.e($unsubscribeUrl).'" style="color:#2563eb;text-decoration:underline;">'.$here.'</a>';
        $copy = __('phase1.confirmation_footer_unsubscribe', ['link' => $link], $resolvedLocale);

        return $html
            .'<p data-email-unsubscribe="1" style="margin:24px 0 0;font-size:14px;line-height:1.5;color:#111111;">'
            .$copy
            .'</p>';
    }
}
