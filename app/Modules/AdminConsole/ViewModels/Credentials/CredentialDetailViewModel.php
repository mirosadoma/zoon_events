<?php

namespace App\Modules\AdminConsole\ViewModels\Credentials;

use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Support\Collection;

final readonly class CredentialDetailViewModel
{
    /**
     * @param  Collection<int, Credential>  $credentials
     * @return array{event: array<string, mixed>, credentials: list<array<string, mixed>>}
     */
    public function index(Event $event, Collection $credentials): array
    {
        return [
            'event' => $this->eventRow($event),
            'credentials' => $credentials->map(fn (Credential $credential): array => $this->credentialRow($credential))->values()->all(),
        ];
    }

    /**
     * @return array{event: array<string, mixed>, credential: array<string, mixed>}
     */
    public function detail(Event $event, Credential $credential): array
    {
        return [
            'event' => $this->eventRow($event),
            'credential' => [
                ...$this->credentialRow($credential),
                'ticket_type_id' => $credential->ticket_type_id,
                'token_version' => $credential->token_version,
                'revoked_at' => $credential->revoked_at?->toIso8601String(),
                'revocation_reason' => $credential->revocation_reason,
                'superseded_by_id' => $credential->superseded_by_id,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function eventRow(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            'status' => $event->status,
        ];
    }

    /** @return array<string, mixed> */
    private function credentialRow(Credential $credential): array
    {
        return [
            'id' => $credential->id,
            'code' => substr($credential->id, -8),
            'attendee_id' => $credential->attendee_id,
            'status' => $credential->status,
            'issued_at' => $credential->issued_at?->toIso8601String(),
            'expires_at' => $credential->expires_at?->toIso8601String(),
        ];
    }
}
