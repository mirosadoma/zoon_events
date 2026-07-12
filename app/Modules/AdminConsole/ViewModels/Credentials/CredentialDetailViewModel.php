<?php

namespace App\Modules\AdminConsole\ViewModels\Credentials;

use App\Modules\AdminConsole\Application\PersonalDataReader;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Support\Collection;

final readonly class CredentialDetailViewModel
{
    public function __construct(private PersonalDataReader $personalData) {}

    /**
     * @param  Collection<int, Credential>  $credentials
     * @return array{event: array<string, mixed>, credentials: list<array<string, mixed>>}
     */
    public function index(Event $event, Collection $credentials): array
    {
        $attendeeLabels = $this->attendeeLabelsForCredentials($event, $credentials);

        return [
            'event' => $this->eventRow($event),
            'credentials' => $credentials->map(
                fn (Credential $credential): array => $this->credentialRow(
                    $credential,
                    $attendeeLabels[(string) $credential->attendee_id] ?? null,
                ),
            )->values()->all(),
        ];
    }

    /**
     * @return array{event: array<string, mixed>, credential: array<string, mixed>}
     */
    public function detail(Event $event, Credential $credential): array
    {
        $attendee = Attendee::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->find($credential->attendee_id);

        return [
            'event' => $this->eventRow($event),
            'credential' => [
                ...$this->credentialRow(
                    $credential,
                    $attendee !== null ? $this->attendeeLabel($attendee) : null,
                ),
                'ticket_type_id' => $credential->ticket_type_id !== null ? (string) $credential->ticket_type_id : null,
                'token_version' => $credential->token_version,
                'revoked_at' => $credential->revoked_at?->toIso8601String(),
                'revocation_reason' => $credential->revocation_reason,
                'superseded_by_id' => $credential->superseded_by_id !== null ? (string) $credential->superseded_by_id : null,
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
    private function credentialRow(Credential $credential, ?string $attendeeLabel): array
    {
        return [
            'id' => (string) $credential->id,
            'code' => substr((string) $credential->id, -8),
            'attendee_id' => (string) $credential->attendee_id,
            'attendee_label' => $attendeeLabel,
            'status' => $credential->status,
            'issued_at' => $credential->issued_at?->toIso8601String(),
            'expires_at' => $credential->expires_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, Credential>  $credentials
     * @return array<string, string>
     */
    private function attendeeLabelsForCredentials(Event $event, Collection $credentials): array
    {
        $attendeeIds = $credentials->pluck('attendee_id')->unique()->filter()->values();

        if ($attendeeIds->isEmpty()) {
            return [];
        }

        return Attendee::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->whereIn('id', $attendeeIds)
            ->get()
            ->mapWithKeys(fn (Attendee $attendee): array => [
                (string) $attendee->id => $this->attendeeLabel($attendee),
            ])
            ->all();
    }

    private function attendeeLabel(Attendee $attendee): string
    {
        return $this->personalData->attendeeDisplayName($attendee) ?: substr((string) $attendee->id, -8);
    }
}
