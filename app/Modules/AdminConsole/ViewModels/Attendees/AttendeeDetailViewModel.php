<?php

namespace App\Modules\AdminConsole\ViewModels\Attendees;

use App\Modules\AdminConsole\Application\PersonalDataReader;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Application\Support\PublicRegistrationUrlBuilder;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

final readonly class AttendeeDetailViewModel
{
    public function __construct(
        private PersonalDataReader $personalData,
        private PublicRegistrationUrlBuilder $registrationUrls,
    ) {}

    /**
     * @param  Collection<int, Attendee>  $attendees
     * @param  array<string, string>  $credentialStatuses
     * @param  array<string, array{id: string, name: array{en: string, ar: string}}>  $currentZones
     * @param  array{search?: string|null, status?: string|null, registration_type?: string|null, event_venue_id?: string|null}  $filters
     * @param  array{page: int, per_page: int, total: int, last_page: int}  $pagination
     * @return array{
     *     event: array<string, mixed>,
     *     attendees: list<array<string, mixed>>,
     *     filters: array{search: string, status: string, registration_type: string, event_venue_id: string},
     *     pagination: array{page: int, per_page: int, total: int, last_page: int}
     * }
     */
    public function index(
        Event $event,
        Collection $attendees,
        array $credentialStatuses = [],
        array $filters = [],
        array $pagination = ['page' => 1, 'per_page' => 15, 'total' => 0, 'last_page' => 1],
        array $currentZones = [],
    ): array {
        return [
            'event' => $this->eventRow($event),
            'attendees' => $attendees->map(fn (Attendee $attendee): array => $this->attendeeRow(
                $attendee,
                $credentialStatuses[$attendee->id] ?? null,
                $currentZones[(string) $attendee->id] ?? null,
            ))->values()->all(),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'registration_type' => (string) ($filters['registration_type'] ?? 'public'),
                'event_venue_id' => (string) ($filters['event_venue_id'] ?? ''),
            ],
            'pagination' => [
                'page' => (int) $pagination['page'],
                'per_page' => (int) $pagination['per_page'],
                'total' => (int) $pagination['total'],
                'last_page' => (int) $pagination['last_page'],
            ],
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
                'entry_card_url' => $this->entryCardUrl($attendee),
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

    private function entryCardUrl(Attendee $attendee): ?string
    {
        if ($attendee->order_id === null) {
            return null;
        }

        $order = Order::query()
            ->whereKey($attendee->order_id)
            ->where('tenant_id', $attendee->tenant_id)
            ->where('event_id', $attendee->event_id)
            ->first();

        if ($order === null || ! is_string($order->public_reference) || $order->public_reference === '') {
            return null;
        }

        $locale = in_array($attendee->preferred_locale, ['ar', 'en'], true)
            ? $attendee->preferred_locale
            : 'en';

        return URL::temporarySignedRoute(
            'public.order.show',
            now()->addDays(90),
            ['locale' => $locale, 'public_reference' => $order->public_reference],
        );
    }

    /** @return array<string, mixed> */
    private function eventRow(Event $event): array
    {
        return [
            'id' => (string) $event->id,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            'status' => $event->status,
            'registration_url' => $this->registrationUrls->forEvent($event),
        ];
    }

    /**
     * @param  array{id: string, name: array{en: string, ar: string}}|null  $currentZone
     * @return array<string, mixed>
     */
    private function attendeeRow(Attendee $attendee, ?string $credentialStatus, ?array $currentZone = null): array
    {
        $displayName = $this->personalData->attendeeDisplayName($attendee);
        $email = $this->personalData->attendeeEmail($attendee);
        $phone = $this->personalData->attendeePhone($attendee);

        return [
            'id' => (string) $attendee->id,
            'status' => $attendee->checkin_status ?? 'not_checked_in',
            'invite_status' => $attendee->invite_status ?? 'registered',
            'locale' => $attendee->preferred_locale,
            'credential_status' => $credentialStatus,
            'label' => $displayName ?: substr((string) $attendee->id, -8),
            'display_name' => $displayName,
            'email' => $email,
            'phone' => $phone,
            'event_venue_id' => $attendee->event_venue_id !== null ? (string) $attendee->event_venue_id : null,
            'current_zone' => $currentZone,
        ];
    }
}
