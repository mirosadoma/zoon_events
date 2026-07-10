<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\IdentityVerification\Application\Actions\ExpireStaleVerifications;
use App\Modules\IdentityVerification\Application\Queries\IdentityGate;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityReasonCode;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityRequirementLevel;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationMethod;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-expiry')]
final class ExpiryTest extends Phase5MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_stale_verification_is_expired_and_blocked_at_enforcement_boundaries(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        config(['identity-verification.verification_validity_days' => 30]);

        $scan = $this->createIssuedCredentialScanFixture(['credential.view']);
        $tenantId = (string) $scan['fixture']['tenant']->id;
        $eventId = (string) $scan['fixture']['event']->id;
        $ticketId = (string) $scan['fixture']['ticket']->id;
        $attendeeId = (string) $scan['credential']->attendee_id;

        IdentityVerificationRequirement::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'ticket_type_id' => $ticketId,
            'level' => IdentityRequirementLevel::REQUIRED_BEFORE_CREDENTIAL,
            'face_fallback_enabled' => true,
        ]);

        IdentityVerification::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'attendee_id' => $attendeeId,
            'method' => IdentityVerificationMethod::GOVERNMENT_IDENTITY,
            'status' => IdentityVerificationStatus::GOV_VERIFIED,
            'verified_name' => 'Expired Subject',
            'verified_nationality' => 'SA',
            'verified_at' => CarbonImmutable::now()->subDays(31),
        ]);

        $result = app(ExpireStaleVerifications::class)->execute(CarbonImmutable::now());
        self::assertSame(1, $result['expired_count']);

        $verification = IdentityVerification::query()->firstOrFail();
        self::assertSame(IdentityVerificationStatus::EXPIRED, $verification->status);

        self::assertTrue(
            AuditLog::query()->where('action', 'identity_verification.result_recorded')->exists(),
        );

        $gate = app(IdentityGate::class);
        $credential = $gate->evaluate($tenantId, $eventId, $attendeeId, 'credential');
        self::assertFalse($credential->satisfied);
        self::assertSame(IdentityReasonCode::EXPIRED, $credential->reasonCode);

        IdentityVerificationRequirement::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', $ticketId)
            ->update(['level' => IdentityRequirementLevel::REQUIRED_BEFORE_GATE]);

        $gateResult = $gate->evaluate($tenantId, $eventId, $attendeeId, 'gate');
        self::assertFalse($gateResult->satisfied);
        self::assertSame(IdentityReasonCode::EXPIRED, $gateResult->reasonCode);
    }
}
