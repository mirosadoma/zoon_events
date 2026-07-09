<?php

namespace App\Modules\AdminConsole\ViewModels\Events;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Application\Publication\PublicationReadiness;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Support\Facades\DB;

final readonly class EventSetupViewModel
{
    public function __construct(private PublicationReadiness $readiness) {}

    /** @return array{event:array<string,mixed>,can:array{manage:bool,publish:bool}} */
    public function make(Event $event, bool $canManage, bool $canPublish): array
    {
        return [
            'event' => [
                'id' => $event->id,
                'slug' => $event->slug,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
                'description' => ['en' => $event->description_en ?? '', 'ar' => $event->description_ar ?? ''],
                'status' => $event->status,
                'tier' => $event->tier,
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
                'readiness' => $this->readiness->missing([
                    ...$event->only(['name_en', 'name_ar', 'timezone', 'start_at', 'end_at', 'registration_opens_at', 'registration_closes_at', 'active_form_version_id']),
                    'active_ticket_types' => DB::table('ticket_types')
                        ->where('tenant_id', $event->tenant_id)
                        ->where('event_id', $event->id)
                        ->where('status', 'active')
                        ->count(),
                    'branding_active' => $event->branding()->where('status', 'active')->exists(),
                ]),
            ],
            'can' => ['manage' => $canManage, 'publish' => $canPublish],
        ];
    }
}
