<?php

namespace App\Modules\IdentityVerification\Application\Actions;

use App\Modules\IdentityVerification\Application\Support\PublicOrderIdentityContext;
use App\Modules\IdentityVerification\Contracts\GovernmentIdentityAdapter;
use App\Modules\IdentityVerification\Domain\Events\IdentityVerificationStarted;
use App\Modules\IdentityVerification\Domain\Results\GovernmentIdentityStartResult;
use App\Modules\IdentityVerification\Domain\ValueObjects\GovernmentIdentityContext;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityReasonCode;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationMethod;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\Shared\Http\Problems\Phase5Problem;
use Illuminate\Support\Facades\DB;

final readonly class StartGovernmentVerificationAction
{
    public function __construct(
        private GovernmentIdentityAdapter $adapter,
        private PublicOrderIdentityContext $context,
    ) {}

    /** @return array{verification:IdentityVerification,start:GovernmentIdentityStartResult} */
    public function execute(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $idempotencyKey,
    ): array {
        if ($this->context->activeConsent($tenantId, $eventId, $attendeeId) === null) {
            throw Phase5Problem::make(IdentityReasonCode::CONSENT_MISSING);
        }

        $start = $this->adapter->startVerification(new GovernmentIdentityContext(
            $tenantId,
            $eventId,
            $attendeeId,
            $idempotencyKey,
        ));

        if (in_array($start->status, ['unavailable', 'failed'], true)
            || $start->reasonCode === IdentityReasonCode::PROVIDER_UNAVAILABLE) {
            throw Phase5Problem::make(IdentityReasonCode::PROVIDER_UNAVAILABLE);
        }

        $verification = DB::transaction(function () use ($tenantId, $eventId, $attendeeId, $start): IdentityVerification {
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
                'method' => IdentityVerificationMethod::GOVERNMENT_IDENTITY,
                'status' => IdentityVerificationStatus::PENDING,
                'provider' => config('identity-verification.default_government_adapter', 'mock'),
                'provider_reference' => $start->reference,
            ])->save();

            event(new IdentityVerificationStarted(
                tenantId: $tenantId,
                eventId: $eventId,
                attendeeId: $attendeeId,
                verificationId: (string) $verification->id,
            ));

            return $verification->refresh();
        });

        if ($start->reference !== null
            && config('identity-verification.default_government_adapter', 'mock') === 'mock') {
            app(HandleGovernmentCallbackAction::class)->execute([
                'reference' => $start->reference,
                'status' => 'verified',
            ]);
            $verification->refresh();
        }

        return compact('verification', 'start');
    }
}
