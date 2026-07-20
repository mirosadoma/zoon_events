<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Inertia\Inertia;
use Inertia\Response;

final class RenderRegistrationSoldOutPage
{
    public function execute(string $locale, Event $event): Response
    {
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';

        return Inertia::render('public/registration/SoldOut', [
            'locale' => $resolvedLocale,
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
