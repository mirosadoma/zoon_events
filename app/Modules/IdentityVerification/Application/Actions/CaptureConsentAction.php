<?php

namespace App\Modules\IdentityVerification\Application\Actions;

use App\Modules\IdentityVerification\Application\Support\ConsentDisclosures;
use App\Modules\IdentityVerification\Domain\Events\IdentityConsentCaptured;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationMethod;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityConsent;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class CaptureConsentAction
{
    /**
     * @return array{consented:bool,consent:IdentityConsent|null,status:string}
     */
    public function execute(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $noticeVersion,
        string $residencyMode,
        bool $consented,
    ): array {
        if (! $consented) {
            return [
                'consented' => false,
                'consent' => null,
                'status' => IdentityVerificationStatus::PENDING,
            ];
        }

        return DB::transaction(function () use ($tenantId, $eventId, $attendeeId, $noticeVersion, $residencyMode): array {
            $consent = IdentityConsent::query()->create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'attendee_id' => $attendeeId,
                'notice_version' => $noticeVersion,
                'disclosures' => ConsentDisclosures::forNotice($noticeVersion),
                'residency_mode' => $residencyMode,
                'consented_at' => CarbonImmutable::now(),
            ]);

            $verification = IdentityVerification::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'attendee_id' => $attendeeId,
                ],
                [
                    'method' => IdentityVerificationMethod::GOVERNMENT_IDENTITY,
                    'status' => IdentityVerificationStatus::PENDING,
                ],
            );

            $verification->forceFill([
                'consent_id' => $consent->id,
                'status' => IdentityVerificationStatus::PENDING,
            ])->save();

            event(new IdentityConsentCaptured(
                tenantId: $tenantId,
                eventId: $eventId,
                attendeeId: $attendeeId,
                consentId: (string) $consent->id,
            ));

            return [
                'consented' => true,
                'consent' => $consent,
                'status' => IdentityVerificationStatus::PENDING,
            ];
        });
    }
}
