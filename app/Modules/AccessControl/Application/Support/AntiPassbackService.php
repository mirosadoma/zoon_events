<?php

namespace App\Modules\AccessControl\Application\Support;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AntiPassbackState;
use Illuminate\Database\UniqueConstraintViolationException;
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

        $keys = [
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->event_id,
            'credential_id' => $event->credential_id,
            'zone_id' => $event->zone_id,
        ];

        // Atomically advance an existing state row only for a strictly newer
        // event, so concurrent / out-of-order entry+exit callbacks cannot regress
        // newer state and enable a passback (data-model.md invariant 5).
        if ($this->advance($keys, $newState, $event) > 0) {
            return;
        }

        // No row advanced: either none exists, or the existing row is newer/equal.
        if (AntiPassbackState::query()->where($keys)->exists()) {
            return;
        }

        try {
            AntiPassbackState::query()->create(array_merge($keys, [
                'id' => (string) Str::ulid(),
                'state' => $newState,
                'last_access_event_id' => $event->id,
                'last_transition_at' => $event->occurred_at,
            ]));
        } catch (UniqueConstraintViolationException) {
            // A concurrent writer inserted the first row; re-apply conditionally.
            $this->advance($keys, $newState, $event);
        }
    }

    /**
     * @param  array<string, string>  $keys
     */
    private function advance(array $keys, string $newState, AccessEvent $event): int
    {
        return AntiPassbackState::query()
            ->where($keys)
            ->where(function ($query) use ($event): void {
                $query->whereNull('last_transition_at')
                    ->orWhere('last_transition_at', '<', $event->occurred_at);
            })
            ->update([
                'state' => $newState,
                'last_access_event_id' => $event->id,
                'last_transition_at' => $event->occurred_at,
            ]);
    }
}
