<?php

namespace App\Modules\IdentityVerification\Application\Actions;

use App\Modules\IdentityVerification\Domain\Events\IdentitySensitiveDataViewed;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityConsent;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class ViewIdentityDataAction
{
    /** @return array<string, mixed> */
    public function execute(TenantContext $context, string $eventId, string $attendeeId): array
    {
        return DB::transaction(function () use ($context, $eventId, $attendeeId): array {
            $verification = IdentityVerification::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->where('attendee_id', $attendeeId)
                ->with(['artifacts'])
                ->firstOrFail();

            event(new IdentitySensitiveDataViewed(
                tenantId: (string) $context->tenant->id,
                eventId: $eventId,
                attendeeId: $attendeeId,
                verificationId: (string) $verification->id,
                actorId: (string) $context->actor->id,
            ));

            $consent = IdentityConsent::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->where('attendee_id', $attendeeId)
                ->whereNull('withdrawn_at')
                ->orderByDesc('consented_at')
                ->first();

            return [
                'verification' => [
                    'id' => (string) $verification->id,
                    'attendee_id' => (string) $verification->attendee_id,
                    'method' => (string) $verification->method,
                    'status' => (string) $verification->status,
                    'verified_at' => $verification->verified_at?->toIso8601String(),
                    'rejection_reason' => $verification->rejection_reason,
                    'retention_until' => $verification->retention_until?->toIso8601String(),
                ],
                'artifacts' => $verification->artifacts
                    ->map(fn ($artifact): array => [
                        'id' => (string) $artifact->id,
                        'artifact_type' => (string) $artifact->artifact_type,
                        'liveness_result' => $artifact->liveness_result,
                        'retention_until' => $artifact->retention_until->toIso8601String(),
                        'purged_at' => $artifact->purged_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
                'consent' => $consent === null ? null : [
                    'notice_version' => (string) $consent->notice_version,
                    'residency_mode' => (string) $consent->residency_mode,
                    'consented_at' => $consent->consented_at?->toIso8601String(),
                ],
                'residency' => [
                    'mode' => (string) config('identity-verification.residency', 'on_premise'),
                    'cross_border_transfer' => (bool) config('identity-verification.cross_border_transfer', false),
                ],
            ];
        });
    }
}
