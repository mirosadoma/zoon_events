<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Domain\EventTier;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;

final readonly class PublicRegistrationUrlBuilder
{
    /** @var list<string> */
    private const SHAREABLE_STATUSES = ['published', 'registration_open', 'registration_closed', 'live'];

    public function forEvent(Event $event, ?string $locale = null): ?string
    {
        if (! $this->isShareable($event)) {
            return null;
        }

        // Private-only events use personal invite links, not a public share URL.
        if ($event->tier === EventTier::Private->value) {
            return null;
        }

        $locale ??= app()->getLocale() === 'ar' ? 'ar' : 'en';

        return url(sprintf('/%s/events/%s/agenda', $locale, $event->slug));
    }

    public function forInvite(Event $event, EventRegistrationInvite $invite, ?string $locale = null): string
    {
        $locale ??= app()->getLocale() === 'ar' ? 'ar' : 'en';

        return url(sprintf('/%s/events/%s/agenda?invite=%s', $locale, $event->slug, $invite->code));
    }

    public function isShareable(Event $event): bool
    {
        return in_array($event->status, self::SHAREABLE_STATUSES, true)
            && $event->active_form_version_id !== null;
    }

    public function requiresInvite(Event $event): bool
    {
        return $event->tier === EventTier::Private->value;
    }
}
