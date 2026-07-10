<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\AccessControl\Application\Actions\AuthorizeGateAction;
use App\Modules\AccessControl\Domain\ValueObjects\AcsIntegrationContext;
use App\Modules\Credentials\Application\Actions\IssueCredential;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityReasonCode;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityRequirementLevel;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-enforcement-status')]
final class EnforcementStatusTest extends Phase5MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_rejected_and_expired_identity_block_issuance_and_gate_entry(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture();
        $tenantId = (string) $scan['fixture']['tenant']->id;
        $eventId = (string) $scan['fixture']['event']->id;
        $attendeeId = (string) $scan['credential']->attendee_id;
        $ticketId = (string) $scan['fixture']['ticket']->id;

        IdentityVerificationRequirement::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'ticket_type_id' => $ticketId,
            'level' => IdentityRequirementLevel::REQUIRED_BEFORE_CREDENTIAL,
            'face_fallback_enabled' => false,
        ]);

        foreach ([IdentityVerificationStatus::REJECTED, IdentityVerificationStatus::EXPIRED] as $status) {
            IdentityVerification::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'attendee_id' => $attendeeId,
                ],
                [
                    'method' => 'gov_identity',
                    'status' => $status,
                    'rejection_reason' => $status === IdentityVerificationStatus::REJECTED ? 'manual_reject' : null,
                ],
            );

            $issued = app(IssueCredential::class)->execute(
                $tenantId,
                $eventId,
                $attendeeId,
                $ticketId,
                CarbonImmutable::parse($scan['fixture']['event']->end_at),
            );
            self::assertNull($issued, "Issuance should be blocked for {$status}");
        }

        IdentityVerificationRequirement::query()
            ->where('event_id', $eventId)
            ->update(['level' => IdentityRequirementLevel::REQUIRED_BEFORE_GATE]);

        $acs = $this->createAcsAuthorizationFixture($scan);
        $ctx = new AcsIntegrationContext($acs['event']->tenant_id, $acs['event']->id, ['authorize']);

        IdentityVerification::query()
            ->where('attendee_id', $attendeeId)
            ->update(['status' => IdentityVerificationStatus::REJECTED]);

        $rejected = app(AuthorizeGateAction::class)->execute(
            $ctx,
            $acs['lane']->external_acs_lane_id,
            $acs['token'],
            'entry',
        );
        self::assertSame('deny', $rejected->decision);
        self::assertSame(IdentityReasonCode::REJECTED, $rejected->reasonCode);

        IdentityVerification::query()
            ->where('attendee_id', $attendeeId)
            ->update(['status' => IdentityVerificationStatus::EXPIRED, 'rejection_reason' => null]);

        $expired = app(AuthorizeGateAction::class)->execute(
            $ctx,
            $acs['lane']->external_acs_lane_id,
            $acs['token'],
            'entry',
        );
        self::assertSame('deny', $expired->decision);
        self::assertSame(IdentityReasonCode::EXPIRED, $expired->reasonCode);
    }
}
