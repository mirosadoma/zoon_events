<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final readonly class PublicRegistrationEventPresenter
{
    public function __construct(
        private EventMediaPresenter $media,
        private EventVenuePresenter $venues,
    ) {}

    /** @return array<string, mixed> */
    public function heroEvent(Event $event, bool $withId = false): array
    {
        $eventMedia = $this->media->forRegistration($event->loadMissing('images'));

        $payload = [
            'slug' => $event->slug,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            'description' => ['en' => $event->description_en ?? '', 'ar' => $event->description_ar ?? ''],
            'timezone' => $event->timezone,
            'start_at' => EventWallClockDateTime::toIso8601($event->start_at, $event->timezone),
            'end_at' => EventWallClockDateTime::toIso8601($event->end_at, $event->timezone),
            'branding' => [
                'brand_reference' => $event->branding()->value('brand_reference'),
                'domain_reference' => $event->branding()->value('domain_reference'),
            ],
            'main_image' => $eventMedia['main_image'],
            'images' => $eventMedia['images'],
            'venues' => $this->venues->forEvent($event),
        ];

        if ($withId) {
            $payload['id'] = (string) $event->id;
        }

        return $payload;
    }
}
