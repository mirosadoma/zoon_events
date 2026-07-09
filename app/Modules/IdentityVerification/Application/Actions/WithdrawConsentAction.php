<?php

namespace App\Modules\IdentityVerification\Application\Actions;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\IdentityVerification\Application\Support\PublicOrderIdentityContext;
use App\Modules\IdentityVerification\Application\Support\RequirementResolver;
use App\Modules\IdentityVerification\Domain\Events\IdentityConsentWithdrawn;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityBiometricArtifact;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityConsent;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\Shared\Http\Problems\Phase5Problem;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class WithdrawConsentAction
{
    public function __construct(
        private PublicOrderIdentityContext $context,
        private RequirementResolver $requirements,
    ) {}

    /** @return array{consent:IdentityConsent,verification:IdentityVerification|null,status:string} */
    public function execute(string $tenantId, string $eventId, string $attendeeId): array
    {
        $consent = $this->context->activeConsent($tenantId, $eventId, $attendeeId);
        if ($consent === null) {
            throw Phase5Problem::make('identity_consent_missing');
        }

        return DB::transaction(function () use ($tenantId, $eventId, $attendeeId, $consent): array {
            $now = CarbonImmutable::now();
            $consent->forceFill(['withdrawn_at' => $now])->save();

            $verification = IdentityVerification::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('attendee_id', $attendeeId)
                ->lockForUpdate()
                ->first();

            if ($verification !== null) {
                IdentityBiometricArtifact::query()
                    ->where('verification_id', $verification->id)
                    ->whereNull('purged_at')
                    ->update([
                        'storage_reference' => 'purged',
                        'purged_at' => $now,
                    ]);

                $attendee = Attendee::query()
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $eventId)
                    ->where('id', $attendeeId)
                    ->first();

                $ticketTypeId = $attendee?->ticket_type_id !== null
                    ? (string) $attendee->ticket_type_id
                    : null;
                $attendeeType = $this->resolveAttendeeType($tenantId, $eventId, $ticketTypeId);
                $stillRequired = $this->requirements->requirementAppliesToAttendee(
                    $tenantId,
                    $eventId,
                    $ticketTypeId,
                    $attendeeType,
                    'credential',
                ) || $this->requirements->requirementAppliesToAttendee(
                    $tenantId,
                    $eventId,
                    $ticketTypeId,
                    $attendeeType,
                    'gate',
                );

                $verification->forceFill([
                    'status' => $stillRequired
                        ? IdentityVerificationStatus::PENDING
                        : IdentityVerificationStatus::NOT_REQUIRED,
                    'consent_id' => null,
                    'provider_reference' => null,
                    'verified_name' => null,
                    'verified_nationality' => null,
                    'verified_at' => null,
                    'rejection_reason' => null,
                    'retention_until' => null,
                ])->save();
            }

            event(new IdentityConsentWithdrawn(
                tenantId: $tenantId,
                eventId: $eventId,
                attendeeId: $attendeeId,
                consentId: (string) $consent->id,
            ));

            return [
                'consent' => $consent->refresh(),
                'verification' => $verification?->refresh(),
                'status' => (string) ($verification?->status ?? IdentityVerificationStatus::PENDING),
            ];
        });
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
