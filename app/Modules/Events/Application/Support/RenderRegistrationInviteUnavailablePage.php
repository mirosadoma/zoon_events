<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Inertia\Inertia;
use Inertia\Response;

final class RenderRegistrationInviteUnavailablePage
{
    public function execute(string $locale, Event $event, string $problemCode): Response
    {
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';

        $reason = match ($problemCode) {
            'invite_required' => 'required',
            'invite_invalid' => 'invalid',
            default => 'inactive',
        };

        return Inertia::render('public/registration/InviteInactive', [
            'locale' => $resolvedLocale,
            'reason' => $reason,
            'event' => [
                'slug' => $event->slug,
                'name' => [
                    'en' => $event->name_en,
                    'ar' => $event->name_ar,
                ],
            ],
        ]);
    }
}
