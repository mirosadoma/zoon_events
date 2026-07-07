<?php

namespace App\Modules\AccessControl\Application\Support;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use DateTimeInterface;

final class AcsRuleEvaluator
{
    public function evaluate(
        string $tenantId,
        string $eventId,
        string $ticketTypeId,
        ?string $attendeeType,
        string $zoneId,
        string $laneId,
        string $direction,
        DateTimeInterface $now,
    ): ?string {
        $rules = AcsAuthorizationRule::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('zone_id', $zoneId)
            ->where('status', 'active')
            ->get();

        if ($rules->isEmpty()) {
            return 'zone_not_permitted';
        }

        $permitting = $rules->first(fn (AcsAuthorizationRule $rule): bool => $this->rulePermits(
            $rule,
            $ticketTypeId,
            $attendeeType,
            $laneId,
            $direction,
            $now,
        ));

        if ($permitting !== null) {
            return null;
        }

        $zoneMatch = $rules->first(fn (AcsAuthorizationRule $rule): bool => $this->matchesTicketAndAttendee($rule, $ticketTypeId, $attendeeType)
            && $this->matchesDirection($rule, $direction));

        if ($zoneMatch === null) {
            return 'zone_not_permitted';
        }

        $laneMatch = $rules->first(fn (AcsAuthorizationRule $rule): bool => $this->matchesTicketAndAttendee($rule, $ticketTypeId, $attendeeType)
            && $this->matchesDirection($rule, $direction)
            && $this->matchesLane($rule, $laneId));

        if ($laneMatch === null) {
            return 'lane_not_permitted';
        }

        return 'outside_time_window';
    }

    public function isAntiPassbackExempt(
        string $tenantId,
        string $eventId,
        string $ticketTypeId,
        ?string $attendeeType,
        string $zoneId,
        string $laneId,
        string $direction,
        DateTimeInterface $now,
    ): bool {
        $rules = AcsAuthorizationRule::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('zone_id', $zoneId)
            ->where('status', 'active')
            ->get();

        $permitting = $rules->first(fn (AcsAuthorizationRule $rule): bool => $this->rulePermits(
            $rule,
            $ticketTypeId,
            $attendeeType,
            $laneId,
            $direction,
            $now,
        ));

        return $permitting?->anti_passback_exempt === true;
    }

    private function rulePermits(
        AcsAuthorizationRule $rule,
        string $ticketTypeId,
        ?string $attendeeType,
        string $laneId,
        string $direction,
        DateTimeInterface $now,
    ): bool {
        return $this->matchesTicketAndAttendee($rule, $ticketTypeId, $attendeeType)
            && $this->matchesDirection($rule, $direction)
            && $this->matchesLane($rule, $laneId)
            && $this->withinWindow($rule, $now);
    }

    private function matchesTicketAndAttendee(AcsAuthorizationRule $rule, string $ticketTypeId, ?string $attendeeType): bool
    {
        if ($rule->ticket_type_id !== null && $rule->ticket_type_id !== $ticketTypeId) {
            return false;
        }

        if ($rule->attendee_type !== null && $rule->attendee_type !== $attendeeType) {
            return false;
        }

        return true;
    }

    private function matchesDirection(AcsAuthorizationRule $rule, string $direction): bool
    {
        return $rule->access_direction === 'bidirectional' || $rule->access_direction === $direction;
    }

    private function matchesLane(AcsAuthorizationRule $rule, string $laneId): bool
    {
        return $rule->lane_id === null || $rule->lane_id === $laneId;
    }

    private function withinWindow(AcsAuthorizationRule $rule, DateTimeInterface $now): bool
    {
        if ($rule->valid_from !== null && $now < $rule->valid_from) {
            return false;
        }

        if ($rule->valid_until !== null && $now > $rule->valid_until) {
            return false;
        }

        return true;
    }
}
