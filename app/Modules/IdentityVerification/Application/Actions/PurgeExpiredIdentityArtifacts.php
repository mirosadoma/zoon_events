<?php

namespace App\Modules\IdentityVerification\Application\Actions;

use App\Modules\IdentityVerification\Domain\Events\IdentityArtifactsPurged;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityBiometricArtifact;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class PurgeExpiredIdentityArtifacts
{
    /** @return array{artifact_count:int,verification_count:int,provider_references_cleared:int} */
    public function execute(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $artifactCount = 0;
        $verificationCount = 0;

        $expiredArtifacts = IdentityBiometricArtifact::query()
            ->where('retention_until', '<=', $now)
            ->whereNull('purged_at')
            ->get();

        $grouped = $expiredArtifacts->groupBy('verification_id');

        DB::transaction(function () use ($grouped, $now, &$artifactCount, &$verificationCount): void {
            foreach ($grouped as $verificationId => $artifacts) {
                $purged = 0;

                foreach ($artifacts as $artifact) {
                    $artifact->forceFill([
                        'storage_reference' => 'purged',
                        'purged_at' => $now,
                    ])->save();
                    $purged++;
                }

                if ($purged === 0) {
                    continue;
                }

                $artifactCount += $purged;
                $verificationCount++;

                $verification = IdentityVerification::query()->find($verificationId);
                if ($verification === null) {
                    continue;
                }

                event(new IdentityArtifactsPurged(
                    tenantId: (string) $verification->tenant_id,
                    eventId: (string) $verification->event_id,
                    verificationId: (string) $verification->id,
                    artifactCount: $purged,
                ));
            }
        });

        $providerCutoff = $now->copy()->subDays(
            (int) config('identity-verification.retention.provider_payload_days', 7),
        );

        $providerReferencesCleared = IdentityVerification::query()
            ->whereNotNull('provider_reference')
            ->where(function ($query) use ($providerCutoff): void {
                $query->where(function ($inner) use ($providerCutoff): void {
                    $inner->whereNotNull('verified_at')
                        ->where('verified_at', '<=', $providerCutoff);
                })->orWhere(function ($inner) use ($providerCutoff): void {
                    $inner->whereNull('verified_at')
                        ->where('updated_at', '<=', $providerCutoff);
                });
            })
            ->update(['provider_reference' => null]);

        IdentityVerification::query()
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', $now)
            ->update([
                'verified_name' => null,
                'verified_nationality' => null,
                'provider_reference' => null,
            ]);

        return [
            'artifact_count' => $artifactCount,
            'verification_count' => $verificationCount,
            'provider_references_cleared' => $providerReferencesCleared,
        ];
    }
}
