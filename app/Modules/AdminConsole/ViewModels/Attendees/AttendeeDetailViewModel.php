<?php

namespace App\Modules\AdminConsole\ViewModels\Attendees;

use App\Modules\AdminConsole\Application\PersonalDataReader;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Support\Collection;

final readonly class AttendeeDetailViewModel
{
    public function __construct(private PersonalDataReader $personalData) {}

    /**
     * @param  Collection<int, Attendee>  $attendees
     * @param  array<string, string>  $credentialStatuses
     * @return array{event: array<string, mixed>, attendees: list<array<string, mixed>>}
     */
    public function index(Event $event, Collection $attendees, array $credentialStatuses = []): array
    {
        return [
            'event' => $this->eventRow($event),
            'attendees' => $attendees->map(fn (Attendee $attendee): array => $this->attendeeRow(
                $attendee,
                $credentialStatuses[$attendee->id] ?? null,
            ))->values()->all(),
        ];
    }

    /**
     * @return array{event: array<string, mixed>, attendee: array<string, mixed>}
     */
    public function detail(Event $event, Attendee $attendee, ?Credential $credential = null): array
    {
        return [
            'event' => $this->eventRow($event),
            'attendee' => [
                ...$this->attendeeRow($attendee, $credential?->status),
                'order_id' => $attendee->order_id !== null ? (string) $attendee->order_id : null,
                'ticket_type_id' => $attendee->ticket_type_id !== null ? (string) $attendee->ticket_type_id : null,
                'registered_at' => $attendee->registered_at?->toIso8601String(),
                'first_checked_in_at' => $attendee->first_checked_in_at?->toIso8601String(),
                'origin' => $attendee->origin,
                'credential' => $credential !== null ? [
                    'id' => (string) $credential->id,
                    'status' => $credential->status,
                    'issued_at' => $credential->issued_at?->toIso8601String(),
                    'expires_at' => $credential->expires_at?->toIso8601String(),
                    'revoked_at' => $credential->revoked_at?->toIso8601String(),
                    'revocation_reason' => $credential->revocation_reason,
                ] : null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function eventRow(Event $event): array
    {
        return [
            'id' => (string) $event->id,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            'status' => $event->status,
        ];
    }

    /** @return array<string, mixed> */
    private function attendeeRow(Attendee $attendee, ?string $credentialStatus): array
    {
        $displayName = $this->personalData->attendeeDisplayName($attendee);
        $email = $this->personalData->attendeeEmail($attendee);
        $phone = $this->personalData->attendeePhone($attendee);

        return [
            'id' => (string) $attendee->id,
            'status' => $attendee->checkin_status ?? 'not_checked_in',
            'locale' => $attendee->preferred_locale,
            'credential_status' => $credentialStatus,
            'label' => $displayName ?: substr((string) $attendee->id, -8),
            'display_name' => $displayName,
            'email' => $email,
            'phone' => $phone,
        ];
    }
}
