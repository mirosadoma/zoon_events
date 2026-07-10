<?php

namespace App\Modules\IdentityVerification\Application\Actions;

use App\Modules\IdentityVerification\Domain\Events\IdentityRequirementConfigured;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class UpsertIdentityRequirementAction
{
    /**
     * @param  array{ticket_type_id?:int|string|null,level:string,face_fallback_enabled?:bool}  $attributes
     */
    public function execute(TenantContext $context, string $eventId, array $attributes): IdentityVerificationRequirement
    {
        $ticketTypeId = isset($attributes['ticket_type_id']) && $attributes['ticket_type_id'] !== null
            ? (string) $attributes['ticket_type_id']
            : null;
        $level = $attributes['level'];
        $faceFallback = (bool) ($attributes['face_fallback_enabled'] ?? false);

        return DB::transaction(function () use ($context, $eventId, $ticketTypeId, $level, $faceFallback): IdentityVerificationRequirement {
            $requirement = IdentityVerificationRequirement::query()->updateOrCreate(
                [
                    'tenant_id' => $context->tenant->id,
                    'event_id' => $eventId,
                    'ticket_type_id' => $ticketTypeId,
                ],
                [
                    'level' => $level,
                    'face_fallback_enabled' => $faceFallback,
                ],
            );

            event(new IdentityRequirementConfigured(
                tenantId: (string) $context->tenant->id,
                eventId: $eventId,
                requirementId: (string) $requirement->id,
                ticketTypeId: $ticketTypeId,
            ));

            return $requirement->refresh();
        });
    }
}
