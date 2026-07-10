<?php

namespace App\Modules\IdentityVerification\Application\Actions;

use App\Modules\IdentityVerification\Contracts\GovernmentIdentityAdapter;
use App\Modules\IdentityVerification\Domain\Events\IdentityVerificationResultRecorded;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class HandleGovernmentCallbackAction
{
    public function __construct(private GovernmentIdentityAdapter $adapter) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{processed:bool,verification:IdentityVerification|null}
     */
    public function execute(array $payload): array
    {
        $callback = $this->adapter->handleCallback($payload);
        $reference = $callback->reference ?? ($payload['reference'] ?? null);

        if ($reference === null || $reference === '') {
            return ['processed' => false, 'verification' => null];
        }

        $verification = IdentityVerification::query()
            ->where('provider_reference', $reference)
            ->first();

        if ($verification === null) {
            return ['processed' => false, 'verification' => null];
        }

        if ($verification->status === IdentityVerificationStatus::GOV_VERIFIED) {
            return ['processed' => true, 'verification' => $verification];
        }

        $result = $this->adapter->fetchResult((string) $reference);
        $attributes = $result->attributes ?? $this->adapter->mapAttributes($callback->raw);

        $status = match ($result->status) {
            'verified' => IdentityVerificationStatus::GOV_VERIFIED,
            'rejected' => IdentityVerificationStatus::REJECTED,
            default => IdentityVerificationStatus::PENDING,
        };

        $verification = DB::transaction(function () use ($verification, $status, $attributes, $result): IdentityVerification {
            $locked = IdentityVerification::query()->lockForUpdate()->findOrFail($verification->id);

            if ($locked->status === IdentityVerificationStatus::GOV_VERIFIED) {
                return $locked;
            }

            $locked->forceFill([
                'status' => $status,
                'verified_name' => $attributes->verifiedName,
                'verified_nationality' => $attributes->verifiedNationality,
                'verified_at' => $status === IdentityVerificationStatus::GOV_VERIFIED
                    ? CarbonImmutable::now()
                    : null,
                'retention_until' => CarbonImmutable::now()->addDays(
                    (int) config('identity-verification.retention.verification_days', 365),
                ),
                'rejection_reason' => $status === IdentityVerificationStatus::REJECTED
                    ? ($result->reasonCode ?? 'identity_rejected')
                    : null,
            ])->save();

            event(new IdentityVerificationResultRecorded(
                tenantId: (string) $locked->tenant_id,
                eventId: (string) $locked->event_id,
                attendeeId: (string) $locked->attendee_id,
                verificationId: (string) $locked->id,
                status: (string) $locked->status,
            ));

            return $locked->refresh();
        });

        return ['processed' => true, 'verification' => $verification];
    }
}
