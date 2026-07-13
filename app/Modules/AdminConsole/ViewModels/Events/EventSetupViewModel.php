<?php

namespace App\Modules\AdminConsole\ViewModels\Events;

use App\Models\User;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Application\Publication\PublicationReadiness;
use App\Modules\Events\Application\Support\EventMediaPresenter;
use App\Modules\Events\Domain\EventRegistrationProfile;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Ticketing\Contracts\ActiveTicketCounter;

final readonly class EventSetupViewModel
{
    public function __construct(
        private PublicationReadiness $readiness,
        private EventMediaPresenter $media,
        private ActiveTicketCounter $tickets,
    ) {}

    /** @return array{event:array<string,mixed>,eventPermissions:array{manage:bool,publish:bool}} */
    public function make(Event $event, bool $canManage, bool $canPublish): array
    {
        $organizer = $event->created_by_user_id !== null
            ? User::query()->find($event->created_by_user_id, ['id', 'name', 'email'])
            : null;

        return [
            'event' => [
                'id' => $event->id,
                'slug' => $event->slug,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
                'description' => ['en' => $event->description_en ?? '', 'ar' => $event->description_ar ?? ''],
                'status' => $event->status,
                'tier' => $event->tier,
                'event_type' => $event->event_type ?? 'seminar',
                'registration_mode' => $event->registration_mode ?? 'free_registration',
                'capabilities' => EventRegistrationProfile::capabilities($event),
                'timezone' => $event->timezone,
                'start_at' => $event->start_at?->toIso8601String(),
                'end_at' => $event->end_at?->toIso8601String(),
                'registration_opens_at' => $event->registration_opens_at?->toIso8601String(),
                'registration_closes_at' => $event->registration_closes_at?->toIso8601String(),
                'capacity' => $event->capacity,
                'location_name' => ['en' => $event->location_name_en ?? '', 'ar' => $event->location_name_ar ?? ''],
                'location_address' => ['en' => $event->location_address_en ?? '', 'ar' => $event->location_address_ar ?? ''],
                'brand_reference' => $event->branding()->value('brand_reference'),
                'domain_reference' => $event->branding()->value('domain_reference'),
                'organizer_user_id' => $event->created_by_user_id !== null ? (string) $event->created_by_user_id : null,
                'organizer' => $organizer instanceof User ? [
                    'id' => (string) $organizer->id,
                    'name' => $organizer->name,
                    'email' => $organizer->email,
                ] : null,
                ...$this->media->forSetup($event->loadMissing('images')),
                'venues' => EventVenue::query()
                    ->where('tenant_id', $event->tenant_id)
                    ->where('event_id', $event->id)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (EventVenue $venue): array => [
                        'id' => (string) $venue->id,
                        'country_id' => $venue->country_id !== null ? (string) $venue->country_id : '',
                        'city_id' => $venue->city_id !== null ? (string) $venue->city_id : '',
                        'name' => ['en' => $venue->name_en, 'ar' => $venue->name_ar],
                        'location_address' => $venue->location_address ?? '',
                        'latitude' => $venue->latitude !== null ? (string) $venue->latitude : '',
                        'longitude' => $venue->longitude !== null ? (string) $venue->longitude : '',
                        'start_at' => $venue->start_at?->toIso8601String(),
                        'end_at' => $venue->end_at?->toIso8601String(),
                        'registration_opens_at' => $venue->registration_opens_at?->toIso8601String(),
                        'registration_closes_at' => $venue->registration_closes_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
                'readiness' => $this->readiness->missingForEvent(
                    $event,
                    $this->tickets->countOrganizerTicketTypesForEvent($event->tenant_id, $event->id),
                ),
            ],
            'eventPermissions' => ['manage' => $canManage, 'publish' => $canPublish],
        ];
    }
}
