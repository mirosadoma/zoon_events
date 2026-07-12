<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final readonly class PublicRegistrationUrlBuilder
{
    /** @var list<string> */
    private const SHAREABLE_STATUSES = ['published', 'registration_open', 'registration_closed', 'live'];

    public function forEvent(Event $event, ?string $locale = null): ?string
    {
        if (! $this->isShareable($event)) {
            return null;
        }

        $locale ??= app()->getLocale() === 'ar' ? 'ar' : 'en';

        return url(sprintf('/%s/events/%s/agenda', $locale, $event->slug));
    }

    public function isShareable(Event $event): bool
    {
        return in_array($event->status, self::SHAREABLE_STATUSES, true)
            && $event->active_form_version_id !== null;
    }
}
