<?php

namespace App\Modules\Events\Application\Publication;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Events\Domain\EventRegistrationProfile;
use App\Modules\Events\Domain\EventStatus;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use Carbon\CarbonImmutable;
use DateTimeZone;

final class PublicationReadiness
{
    /**
     * @param array{
     *   name_en?:string,name_ar?:string,timezone?:string,start_at?:string,
     *   end_at?:string,registration_opens_at?:string,registration_closes_at?:string,
     *   agenda_items?:int,active_form_version_id?:string,active_ticket_types?:int,branding_active?:bool,
     *   active_badge_template?:bool,tier?:string,registration_mode?:string,configured_categories?:int
     * } $event
     * @return list<string>
     */
    public function missing(array $event): array
    {
        $missing = [];
        foreach (['name_en', 'name_ar', 'timezone', 'start_at', 'end_at', 'registration_opens_at', 'registration_closes_at'] as $key) {
            if (trim((string) ($event[$key] ?? '')) === '') {
                $missing[] = $key;
            }
        }

        if (($event['agenda_items'] ?? 0) < 1) {
            $missing[] = 'published_agenda';
        }

        if (trim((string) ($event['active_form_version_id'] ?? '')) === '') {
            $missing[] = 'active_form_version_id';
        }

        if (EventRegistrationProfile::requiresTicketConfiguration(
            (string) ($event['tier'] ?? 'private'),
            (string) ($event['registration_mode'] ?? 'free_registration'),
        ) && ($event['active_ticket_types'] ?? 0) < 1) {
            $missing[] = 'active_ticket_type';
        }

        if (($event['configured_categories'] ?? 0) < 1) {
            $missing[] = 'event_categories';
        }

        if (($event['branding_active'] ?? false) !== true) {
            $missing[] = 'active_branding';
        }

        if (($event['active_badge_template'] ?? false) !== true) {
            $missing[] = 'active_badge_template';
        }

        if (isset($event['timezone']) && ! in_array($event['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $missing[] = 'valid_timezone';
        }

        try {
            $start = CarbonImmutable::parse((string) ($event['start_at'] ?? ''));
            $end = CarbonImmutable::parse((string) ($event['end_at'] ?? ''));
            $opens = CarbonImmutable::parse((string) ($event['registration_opens_at'] ?? ''));
            $closes = CarbonImmutable::parse((string) ($event['registration_closes_at'] ?? ''));
            if (! ($opens->lessThanOrEqualTo($closes) && $closes->lessThanOrEqualTo($end) && $start->isBefore($end))) {
                $missing[] = 'valid_schedule';
            }
        } catch (\Throwable) {
            if (! in_array('valid_schedule', $missing, true)) {
                $missing[] = 'valid_schedule';
            }
        }

        return array_values(array_unique($missing));
    }

    /** @return list<string> */
    public function missingForEvent(Event $event, int $activeTicketTypes): array
    {
        $missing = $this->missing([
            ...$event->only([
                'name_en', 'name_ar', 'timezone', 'start_at', 'end_at',
                'registration_opens_at', 'registration_closes_at', 'active_form_version_id',
                'tier', 'registration_mode',
            ]),
            'agenda_items' => $event->agendaItems()->count(),
            'active_ticket_types' => $activeTicketTypes,
            'branding_active' => $event->branding()->where('status', 'active')->exists(),
            'active_badge_template' => BadgeTemplate::query()
                ->where('tenant_id', $event->tenant_id)
                ->where('event_id', $event->id)
                ->where('status', 'active')
                ->exists(),
            'configured_categories' => EventCategory::query()
                ->where('event_id', $event->id)
                ->whereHas('venues.days')
                ->count(),
        ]);

        $status = EventStatus::tryFrom((string) $event->status);

        if ($status !== null && $status !== EventStatus::Draft && $status !== EventStatus::Configured) {
            $missing[] = 'status_'.$event->status;
        }

        return array_values(array_unique($missing));
    }

    /** @param array<string,mixed> $event */
    public function isReady(array $event): bool
    {
        return $this->missing($event) === [];
    }
}
