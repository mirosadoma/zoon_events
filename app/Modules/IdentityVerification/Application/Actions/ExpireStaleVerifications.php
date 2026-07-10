<?php

namespace App\Modules\IdentityVerification\Application\Actions;

use App\Modules\IdentityVerification\Domain\Events\IdentityVerificationResultRecorded;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ExpireStaleVerifications
{
    /** @return array{expired_count:int} */
    public function execute(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $cutoff = $now->copy()->subDays(
            (int) config('identity-verification.verification_validity_days', 365),
        );
        $expiredCount = 0;

        IdentityVerification::query()
            ->whereIn('status', [
                IdentityVerificationStatus::GOV_VERIFIED,
                IdentityVerificationStatus::FACE_VERIFIED,
                IdentityVerificationStatus::MANUALLY_APPROVED,
            ])
            ->whereNotNull('verified_at')
            ->where('verified_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($verifications) use (&$expiredCount): void {
                foreach ($verifications as $verification) {
                    DB::transaction(function () use ($verification, &$expiredCount): void {
                        $locked = IdentityVerification::query()
                            ->lockForUpdate()
                            ->find($verification->id);

                        if ($locked === null) {
                            return;
                        }

                        if (! in_array((string) $locked->status, [
                            IdentityVerificationStatus::GOV_VERIFIED,
                            IdentityVerificationStatus::FACE_VERIFIED,
                            IdentityVerificationStatus::MANUALLY_APPROVED,
                        ], true)) {
                            return;
                        }

                        $locked->forceFill([
                            'status' => IdentityVerificationStatus::EXPIRED,
                        ])->save();

                        event(new IdentityVerificationResultRecorded(
                            tenantId: (string) $locked->tenant_id,
                            eventId: (string) $locked->event_id,
                            attendeeId: (string) $locked->attendee_id,
                            verificationId: (string) $locked->id,
                            status: IdentityVerificationStatus::EXPIRED,
                        ));

                        $expiredCount++;
                    });
                }
            });

        return ['expired_count' => $expiredCount];
    }
}
