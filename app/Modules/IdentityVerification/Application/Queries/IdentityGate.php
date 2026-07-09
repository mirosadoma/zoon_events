<?php

namespace App\Modules\IdentityVerification\Application\Queries;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\IdentityVerification\Application\Support\RequirementResolver;
use App\Modules\IdentityVerification\Domain\Results\IdentityGateResult;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityReasonCode;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;

final readonly class IdentityGate
{
    public function __construct(private RequirementResolver $requirements) {}

    public function evaluate(string $tenantId, string $eventId, string $attendeeId, string $boundary): IdentityGateResult
    {
        $attendee = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('id', $attendeeId)
            ->first();

        $ticketTypeId = $attendee?->ticket_type_id !== null ? (string) $attendee->ticket_type_id : null;
        $level = $this->requirements->resolve($tenantId, $eventId, $ticketTypeId);
        $attendeeType = $this->resolveAttendeeType($tenantId, $eventId, $ticketTypeId);

        if (! $this->requirements->requirementAppliesToAttendee(
            $tenantId,
            $eventId,
            $ticketTypeId,
            $attendeeType,
            $boundary,
        )) {
            return new IdentityGateResult(
                satisfied: true,
                requirementLevel: $level,
                status: IdentityVerificationStatus::NOT_REQUIRED,
            );
        }

        $verification = IdentityVerification::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('attendee_id', $attendeeId)
            ->first();

        if ($verification === null) {
            return new IdentityGateResult(
                satisfied: false,
                requirementLevel: $level,
                status: IdentityVerificationStatus::PENDING,
                reasonCode: IdentityReasonCode::NOT_VERIFIED,
            );
        }

        $status = (string) $verification->status;
        if (in_array($status, [
            IdentityVerificationStatus::GOV_VERIFIED,
            IdentityVerificationStatus::FACE_VERIFIED,
            IdentityVerificationStatus::MANUALLY_APPROVED,
        ], true)) {
            return new IdentityGateResult(true, $level, $status);
        }

        $reasonCode = match ($status) {
            IdentityVerificationStatus::EXPIRED => IdentityReasonCode::EXPIRED,
            IdentityVerificationStatus::REJECTED => IdentityReasonCode::REJECTED,
            default => IdentityReasonCode::NOT_VERIFIED,
        };

        return new IdentityGateResult(false, $level, $status, $reasonCode);
    }

    private function resolveAttendeeType(string $tenantId, string $eventId, ?string $ticketTypeId): ?string
    {
        if ($ticketTypeId === null) {
            return null;
        }

        $attendeeType = TicketType::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('id', $ticketTypeId)
            ->value('attendee_type');

        return is_string($attendeeType) && $attendeeType !== '' ? $attendeeType : null;
    }
}
