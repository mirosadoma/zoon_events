<?php

namespace App\Modules\Events\Application\Publication;

use App\Modules\Events\Domain\EventRegistrationProfile;
use App\Modules\Events\Domain\EventStatus;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Carbon\CarbonImmutable;
use DateTimeZone;

final class PublicationReadiness
{
    /**
     * @param array{
     *   name_en?:string,name_ar?:string,timezone?:string,start_at?:string,
     *   end_at?:string,registration_opens_at?:string,registration_closes_at?:string,
     *   active_form_version_id?:string,active_ticket_types?:int,branding_active?:bool,
     *   main_image_path?:string,tier?:string,registration_mode?:string
     * } $event
     * @return list<string>
     */
    public function missing(array $event): array
    {
        $missing = [];
        foreach (['name_en', 'name_ar', 'timezone', 'start_at', 'end_at', 'registration_opens_at', 'registration_closes_at', 'active_form_version_id'] as $key) {
            if (trim((string) ($event[$key] ?? '')) === '') {
                $missing[] = $key;
            }
        }

        if (EventRegistrationProfile::requiresTicketConfiguration(
            (string) ($event['tier'] ?? 'corporate'),
            (string) ($event['registration_mode'] ?? 'free_registration'),
        ) && ($event['active_ticket_types'] ?? 0) < 1) {
            $missing[] = 'active_ticket_type';
        }

        if (($event['branding_active'] ?? false) !== true) {
            $missing[] = 'active_branding';
        }
        if (trim((string) ($event['main_image_path'] ?? '')) === '') {
            $missing[] = 'main_image';
        }
        if (isset($event['timezone']) && ! in_array($event['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $missing[] = 'valid_timezone';
        }

        try {
            $start = CarbonImmutable::parse((string) ($event['start_at'] ?? ''));
            $end = CarbonImmutable::parse((string) ($event['end_at'] ?? ''));
            $opens = CarbonImmutable::parse((string) ($event['registration_opens_at'] ?? ''));
            $closes = CarbonImmutable::parse((string) ($event['registration_closes_at'] ?? ''));
            if (! ($opens->isBefore($closes) && $closes->lessThanOrEqualTo($end) && $start->isBefore($end))) {
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
                'main_image_path', 'tier', 'registration_mode',
            ]),
            'active_ticket_types' => $activeTicketTypes,
            'branding_active' => $event->branding()->where('status', 'active')->exists(),
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
