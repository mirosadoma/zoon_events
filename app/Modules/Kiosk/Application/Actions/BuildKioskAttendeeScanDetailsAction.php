<?php

namespace App\Modules\Kiosk\Application\Actions;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Application\Support\EventMediaPresenter;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationSubmission;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;

final readonly class BuildKioskAttendeeScanDetailsAction
{
    public function __construct(
        private PersonalDataCipher $cipher,
        private EventMediaPresenter $media,
    ) {}

    /**
     * @return array{
     *   event: ?array<string, mixed>,
     *   assigned_venue: ?array<string, mixed>,
     *   registration: list<array{key:string,label:string,label_ar:string,value:string}>
     * }
     */
    public function execute(string $tenantId, string $eventId, ?string $attendeeId): array
    {
        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->find($eventId);

        $attendee = $attendeeId
            ? Attendee::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->find($attendeeId)
            : null;

        return [
            'event' => $event !== null ? $this->eventPayload($event) : null,
            'assigned_venue' => $this->assignedVenue($attendee),
            'registration' => $this->registrationRows($tenantId, $eventId, $attendee, $event),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function eventPayload(Event $event): array
    {
        $timezone = (string) ($event->timezone ?: 'UTC');
        $media = $this->media->forRegistration($event);

        return [
            'id' => (string) $event->id,
            'code' => (string) ($event->code ?: ''),
            'name' => (string) ($event->name_en ?: $event->name_ar ?: 'Event'),
            'name_ar' => (string) ($event->name_ar ?: ''),
            'description' => (string) ($event->description_en ?: ''),
            'description_ar' => (string) ($event->description_ar ?: ''),
            'timezone' => $timezone,
            'start_at' => EventWallClockDateTime::toIso8601($event->start_at, $timezone),
            'end_at' => EventWallClockDateTime::toIso8601($event->end_at, $timezone),
            'location' => (string) ($event->location_name_en ?: $event->location_name_ar ?: ''),
            'location_ar' => (string) ($event->location_name_ar ?: ''),
            'address' => (string) ($event->location_address_en ?: $event->location_address_ar ?: ''),
            'main_image' => $this->absoluteUrl($media['main_image'] ?? null),
            'images' => array_values(array_filter(array_map(
                fn (?string $url): ?string => $this->absoluteUrl($url),
                $media['images'] ?? [],
            ))),
            'venues' => $event->venues()
                ->orderBy('sort_order')
                ->get(['id', 'name_en', 'name_ar', 'start_at', 'end_at'])
                ->map(fn (EventVenue $venue): array => [
                    'id' => (string) $venue->id,
                    'name' => (string) ($venue->name_en ?: $venue->name_ar ?: 'Venue'),
                    'name_ar' => (string) ($venue->name_ar ?: ''),
                    'start_at' => EventWallClockDateTime::toIso8601($venue->start_at, $timezone),
                    'end_at' => EventWallClockDateTime::toIso8601($venue->end_at, $timezone),
                ])->values()->all(),
        ];
    }

    /**
     * @return array{id:string,name:string,name_ar:string}|null
     */
    private function assignedVenue(?Attendee $attendee): ?array
    {
        if ($attendee?->event_venue_id === null) {
            return null;
        }

        $venue = EventVenue::query()->find($attendee->event_venue_id);
        if ($venue === null) {
            return null;
        }

        return [
            'id' => (string) $venue->id,
            'name' => (string) ($venue->name_en ?: $venue->name_ar ?: 'Venue'),
            'name_ar' => (string) ($venue->name_ar ?: ''),
        ];
    }

    /**
     * @return list<array{key:string,label:string,label_ar:string,value:string}>
     */
    private function registrationRows(
        string $tenantId,
        string $eventId,
        ?Attendee $attendee,
        ?Event $event,
    ): array {
        if ($attendee === null) {
            return [];
        }

        $answers = $this->answers($tenantId, $eventId, $attendee);
        $labels = $this->formLabels($event);
        $rows = [];

        $ticket = $this->ticketLabel($tenantId, $eventId, $attendee);
        if ($ticket !== null) {
            $rows[] = [
                'key' => 'ticket_type',
                'label' => 'Ticket type',
                'label_ar' => 'نوع التذكرة',
                'value' => $ticket,
            ];
        }

        foreach ($answers as $key => $raw) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $value = $this->stringifyAnswer($raw);
            if ($value === '') {
                continue;
            }

            $meta = $labels[$key] ?? null;
            $rows[] = [
                'key' => $key,
                'label' => (string) ($meta['label_en'] ?? $this->humanize($key)),
                'label_ar' => (string) ($meta['label_ar'] ?? $meta['label_en'] ?? $this->humanize($key)),
                'value' => $value,
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function answers(string $tenantId, string $eventId, Attendee $attendee): array
    {
        if ($attendee->submission_id === null) {
            return [];
        }

        $submission = RegistrationSubmission::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($attendee->submission_id);

        if ($submission === null || $submission->answers_ciphertext === null || $submission->answers_ciphertext === 'anonymized') {
            return [];
        }

        try {
            $json = $this->cipher->decrypt(
                ['key_id' => $submission->encryption_key_id, 'ciphertext' => $submission->answers_ciphertext],
                "{$tenantId}:{$eventId}:submission",
            );
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, array{label_en:string,label_ar:string}>
     */
    private function formLabels(?Event $event): array
    {
        if ($event === null) {
            return [];
        }

        $query = RegistrationFormVersion::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id);

        $version = $event->active_form_version_id !== null
            ? (clone $query)->whereKey($event->active_form_version_id)->first()
            : null;

        $version ??= (clone $query)->where('status', 'published')->latest('version')->first()
            ?? $query->latest('version')->first();

        if ($version === null || ! is_array($version->fields)) {
            return [];
        }

        $labels = [];
        foreach ($version->fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $key = is_string($field['key'] ?? null) ? trim($field['key']) : '';
            if ($key === '') {
                continue;
            }
            $labels[$key] = [
                'label_en' => is_string($field['label_en'] ?? null) ? $field['label_en'] : $key,
                'label_ar' => is_string($field['label_ar'] ?? null) ? $field['label_ar'] : (is_string($field['label_en'] ?? null) ? $field['label_en'] : $key),
            ];
        }

        return $labels;
    }

    private function ticketLabel(string $tenantId, string $eventId, Attendee $attendee): ?string
    {
        if ($attendee->ticket_type_id === null) {
            return null;
        }

        $ticket = TicketType::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($attendee->ticket_type_id);

        if ($ticket === null) {
            return null;
        }

        return (string) ($ticket->name_en ?: $ticket->name_ar ?: $ticket->code ?: '');
    }

    private function stringifyAnswer(mixed $raw): string
    {
        if ($raw === null) {
            return '';
        }

        if (is_bool($raw)) {
            return $raw ? 'Yes' : 'No';
        }

        if (is_scalar($raw)) {
            return trim((string) $raw);
        }

        if (is_array($raw)) {
            $parts = [];
            foreach ($raw as $item) {
                if (is_scalar($item)) {
                    $parts[] = (string) $item;
                }
            }

            return implode(', ', array_filter($parts, fn (string $part): bool => trim($part) !== ''));
        }

        return '';
    }

    private function humanize(string $key): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }

    private function absoluteUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }
}
