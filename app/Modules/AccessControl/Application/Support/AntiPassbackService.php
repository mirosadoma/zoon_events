<?php

namespace App\Modules\AccessControl\Application\Support;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AntiPassbackState;
use Illuminate\Support\Str;

final class AntiPassbackService
{
    public function isInside(string $tenantId, string $eventId, string $credentialId, string $zoneId): bool
    {
        $state = AntiPassbackState::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('credential_id', $credentialId)
            ->where('zone_id', $zoneId)
            ->first();

        return $state?->state === 'inside';
    }

    public function applyEvent(AccessEvent $event): void
    {
        if ($event->credential_id === null || $event->zone_id === null) {
            return;
        }

        if (! in_array($event->event_type, ['entry', 'exit'], true)) {
            return;
        }

        $newState = $event->event_type === 'entry' ? 'inside' : 'outside';

        $existing = AntiPassbackState::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->event_id)
            ->where('credential_id', $event->credential_id)
            ->where('zone_id', $event->zone_id)
            ->first();

        if ($existing !== null
            && $existing->last_transition_at !== null
            && $event->occurred_at < $existing->last_transition_at) {
            return;
        }

        AntiPassbackState::query()->updateOrCreate(
            [
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->event_id,
                'credential_id' => $event->credential_id,
                'zone_id' => $event->zone_id,
            ],
            [
                'id' => $existing?->id ?? (string) Str::ulid(),
                'state' => $newState,
                'last_access_event_id' => $event->id,
                'last_transition_at' => $event->occurred_at,
            ],
        );
    }
}
