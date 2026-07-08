<?php

namespace App\Modules\AccessControl\Application\Actions;

use App\Modules\AccessControl\Domain\Events\AcsRuleCreated;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;

final readonly class CreateAcsRuleAction
{
    public function __construct(private AuditedTransaction $audited) {}

    /**
     * @param  array{
     *     zone_id: string,
     *     access_direction: string,
     *     ticket_type_id?: string|null,
     *     attendee_type?: string|null,
     *     lane_id?: string|null,
     *     anti_passback_exempt?: bool,
     *     valid_from?: \DateTimeInterface|null,
     *     valid_until?: \DateTimeInterface|null,
     *     status?: string,
     * }  $data
     */
    public function execute(string $tenantId, string $eventId, array $data): AcsAuthorizationRule
    {
        $validFrom = $data['valid_from'] ?? null;
        $validUntil = $data['valid_until'] ?? null;

        if ($validFrom !== null && $validUntil !== null && $validFrom > $validUntil) {
            throw Phase4Problem::make('acs_invalid_time_window');
        }

        $zone = AcsZone::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('id', $data['zone_id'])
            ->first();

        if ($zone === null) {
            abort(404);
        }

        if (isset($data['lane_id']) && $data['lane_id'] !== null) {
            $laneExists = AcsLane::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('id', $data['lane_id'])
                ->exists();

            if (! $laneExists) {
                abort(404);
            }
        }

        if (isset($data['ticket_type_id']) && $data['ticket_type_id'] !== null) {
            $ticketExists = TicketType::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('id', $data['ticket_type_id'])
                ->exists();

            if (! $ticketExists) {
                abort(404);
            }
        }

        return $this->audited->run(
            fn (): AcsAuthorizationRule => AcsAuthorizationRule::query()->create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'ticket_type_id' => $data['ticket_type_id'] ?? null,
                'attendee_type' => $data['attendee_type'] ?? null,
                'zone_id' => $zone->id,
                'lane_id' => $data['lane_id'] ?? null,
                'access_direction' => $data['access_direction'],
                'anti_passback_exempt' => $data['anti_passback_exempt'] ?? false,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'status' => $data['status'] ?? 'active',
            ]),
            fn (AcsAuthorizationRule $rule): mixed => event(new AcsRuleCreated($tenantId, $eventId, $rule->id)),
        );
    }
}
