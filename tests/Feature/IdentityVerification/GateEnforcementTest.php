<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\AccessControl\Application\Actions\AuthorizeGateAction;
use App\Modules\AccessControl\Domain\ValueObjects\AcsIntegrationContext;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityRequirementLevel;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesIdentityAttendeeFixture;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-gate-enforcement')]
final class GateEnforcementTest extends Phase5MySqlTestCase
{
    use CreatesIdentityAttendeeFixture;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_unverified_attendee_is_denied_at_gate_then_allowed_after_verification(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture();
        $eventId = (string) $scan['fixture']['event']->id;
        $attendeeId = (string) $scan['credential']->attendee_id;

        IdentityVerificationRequirement::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'event_id' => $eventId,
            'ticket_type_id' => null,
            'level' => IdentityRequirementLevel::REQUIRED_BEFORE_GATE,
            'face_fallback_enabled' => false,
        ]);

        $acs = $this->createAcsAuthorizationFixture($scan);
        $ctx = new AcsIntegrationContext($acs['event']->tenant_id, $acs['event']->id, ['authorize']);

        $denied = app(AuthorizeGateAction::class)->execute(
            $ctx,
            $acs['lane']->external_acs_lane_id,
            $acs['token'],
            'entry',
        );

        self::assertSame('deny', $denied->decision);
        self::assertSame('identity_not_verified', $denied->reasonCode);
        self::assertSame(
            'identity_not_verified',
            AccessEvent::query()->latest('id')->value('reason_code'),
        );

        $context = [
            'fixture' => $scan['fixture'],
            'attendee' => Attendee::query()->findOrFail($attendeeId),
            'accessToken' => $scan['accessToken'],
        ];
        $base = "/api/v1/tenant/events/{$eventId}/attendees/{$attendeeId}/identity";

        $this->postJson(
            "{$base}/consent",
            ['notice_version' => 'identity-v1', 'residency_mode' => 'on_premise', 'consented' => true],
            $this->identityAttendeeHeaders($context, 'identity-gate-consent-'.Str::ulid()),
        )->assertCreated();

        $this->postJson(
            "{$base}/verification",
            [],
            $this->identityAttendeeHeaders($context, 'identity-gate-start-'.Str::ulid()),
        )->assertAccepted();

        $allowed = app(AuthorizeGateAction::class)->execute(
            $ctx,
            $acs['lane']->external_acs_lane_id,
            $acs['token'],
            'entry',
        );

        self::assertSame('allow', $allowed->decision);
        self::assertSame('allowed', $allowed->reasonCode);
    }
}
