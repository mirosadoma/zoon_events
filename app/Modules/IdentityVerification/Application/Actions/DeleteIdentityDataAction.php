<?php

namespace App\Modules\IdentityVerification\Application\Actions;

use App\Modules\IdentityVerification\Domain\Events\IdentitySensitiveDataDeleted;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityBiometricArtifact;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class DeleteIdentityDataAction
{
    public function execute(
        TenantContext $context,
        string $eventId,
        string $attendeeId,
        string $reason,
    ): IdentityVerification {
        return DB::transaction(function () use ($context, $eventId, $attendeeId, $reason): IdentityVerification {
            $verification = IdentityVerification::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->where('attendee_id', $attendeeId)
                ->lockForUpdate()
                ->firstOrFail();

            $now = CarbonImmutable::now();

            IdentityBiometricArtifact::query()
                ->where('verification_id', $verification->id)
                ->whereNull('purged_at')
                ->update([
                    'storage_reference' => 'purged',
                    'purged_at' => $now,
                ]);

            $verification->forceFill([
                'status' => IdentityVerificationStatus::PENDING,
                'provider_reference' => null,
                'verified_name' => null,
                'verified_nationality' => null,
                'verified_at' => null,
                'rejection_reason' => trim($reason),
                'retention_until' => null,
            ])->save();

            event(new IdentitySensitiveDataDeleted(
                tenantId: (string) $context->tenant->id,
                eventId: $eventId,
                attendeeId: $attendeeId,
                verificationId: (string) $verification->id,
                actorId: (string) $context->actor->id,
            ));

            return $verification->refresh();
        });
    }
}
