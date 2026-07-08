<?php

namespace App\Modules\IdentityVerification\Application\Support;

use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityRequirementLevel;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;

final readonly class RequirementResolver
{
    public function resolve(string $tenantId, string $eventId, ?string $ticketTypeId): string
    {
        if ($ticketTypeId !== null) {
            $ticketOverride = IdentityVerificationRequirement::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('ticket_type_id', $ticketTypeId)
                ->value('level');

            if (is_string($ticketOverride) && $ticketOverride !== '') {
                return $ticketOverride;
            }
        }

        $eventDefault = IdentityVerificationRequirement::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereNull('ticket_type_id')
            ->value('level');

        return is_string($eventDefault) && $eventDefault !== ''
            ? $eventDefault
            : IdentityRequirementLevel::NOT_REQUIRED;
    }

    public function faceFallbackEnabled(string $tenantId, string $eventId, ?string $ticketTypeId): bool
    {
        if ($ticketTypeId !== null) {
            $ticketOverride = IdentityVerificationRequirement::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('ticket_type_id', $ticketTypeId)
                ->first(['face_fallback_enabled']);

            if ($ticketOverride !== null) {
                return (bool) $ticketOverride->face_fallback_enabled;
            }
        }

        return (bool) IdentityVerificationRequirement::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereNull('ticket_type_id')
            ->value('face_fallback_enabled');
    }
}
