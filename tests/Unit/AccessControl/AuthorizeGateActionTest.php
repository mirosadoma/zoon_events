<?php

namespace Tests\Unit\AccessControl;

use App\Exceptions\FoundationException;
use App\Modules\AccessControl\Application\Actions\AuthorizeGateAction;
use App\Modules\AccessControl\Domain\ValueObjects\AcsIntegrationContext;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\AccessControl\Testing\FakeAcsAdapter;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-authorization')]
final class AuthorizeGateActionTest extends Phase4MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_admission_lane_records_a_linked_scan_event(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan, admissionLane: true);
        $ctx = new AcsIntegrationContext($acs['event']->tenant_id, $acs['event']->id, ['authorize']);

        $result = app(AuthorizeGateAction::class)->execute(
            $ctx,
            $acs['lane']->external_acs_lane_id,
            $acs['token'],
            'entry',
        );

        self::assertSame('allow', $result->decision);
        self::assertNotNull($result->scanEventId);
    }

    public function test_throws_acs_lane_unmapped_for_unknown_external_lane(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $ctx = new AcsIntegrationContext($acs['event']->tenant_id, $acs['event']->id, ['authorize']);

        try {
            app(AuthorizeGateAction::class)->execute(
                $ctx,
                'unknown-lane-id',
                $acs['token'],
                'entry',
            );
            self::fail('Expected acs_lane_unmapped.');
        } catch (FoundationException $exception) {
            self::assertSame('acs_lane_unmapped', $exception->problemCode);
        }
    }

    public function test_allows_a_valid_credential_and_records_one_decision_access_event(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $ctx = new AcsIntegrationContext($acs['event']->tenant_id, $acs['event']->id, ['authorize']);

        $result = app(AuthorizeGateAction::class)->execute(
            $ctx,
            $acs['lane']->external_acs_lane_id,
            $acs['token'],
            'entry',
        );

        self::assertSame('allow', $result->decision);
        self::assertSame('allowed', $result->reasonCode);
        self::assertSame(1, AccessEvent::query()->where('event_type', 'decision')->count());
    }

    public function test_denies_expired_revoked_and_unknown_credentials(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $ctx = new AcsIntegrationContext($acs['event']->tenant_id, $acs['event']->id, ['authorize']);

        $credential = Credential::query()->findOrFail($acs['credential']->id);
        $credential->forceFill(['expires_at' => now()->addSecond()])->save();
        $this->travel(2)->seconds();

        $expired = app(AuthorizeGateAction::class)->execute(
            $ctx,
            $acs['lane']->external_acs_lane_id,
            $acs['token'],
            'entry',
        );
        self::assertSame('deny', $expired->decision);
        self::assertSame('credential_expired', $expired->reasonCode);

        $credential->forceFill(['expires_at' => now()->addDay(), 'status' => 'revoked', 'revoked_at' => now()])->save();

        $revoked = app(AuthorizeGateAction::class)->execute(
            $ctx,
            $acs['lane']->external_acs_lane_id,
            $acs['token'],
            'entry',
        );
        self::assertSame('deny', $revoked->decision);
        self::assertSame('credential_revoked', $revoked->reasonCode);

        $unknown = app(AuthorizeGateAction::class)->execute(
            $ctx,
            $acs['lane']->external_acs_lane_id,
            'not-a-valid-credential-token',
            'entry',
        );
        self::assertSame('deny', $unknown->decision);
        self::assertSame('credential_unknown', $unknown->reasonCode);
    }

    public function test_denies_when_rule_evaluator_returns_zone_not_permitted(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $acs['rule']->delete();
        $ctx = new AcsIntegrationContext($acs['event']->tenant_id, $acs['event']->id, ['authorize']);

        $result = app(AuthorizeGateAction::class)->execute(
            $ctx,
            $acs['lane']->external_acs_lane_id,
            $acs['token'],
            'entry',
        );

        self::assertSame('deny', $result->decision);
        self::assertSame('zone_not_permitted', $result->reasonCode);
    }

    public function test_applies_fail_open_and_fail_closed_when_adapter_is_unavailable(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $ctx = new AcsIntegrationContext($acs['event']->tenant_id, $acs['event']->id, ['authorize']);
        $adapter = app(FakeAcsAdapter::class);
        $adapter->forceUnavailable(true);

        $failOpenZone = AcsZone::query()->findOrFail($acs['zone']->id);
        $failOpenZone->forceFill(['unavailability_mode' => 'fail_open'])->save();

        $open = app(AuthorizeGateAction::class)->execute(
            $ctx,
            $acs['lane']->external_acs_lane_id,
            $acs['token'],
            'entry',
        );
        self::assertSame('allow', $open->decision);
        self::assertSame('acs_unavailable_fail_open', $open->reasonCode);

        $failOpenZone->forceFill(['unavailability_mode' => 'fail_closed'])->save();

        $closed = app(AuthorizeGateAction::class)->execute(
            $ctx,
            $acs['lane']->external_acs_lane_id,
            $acs['token'],
            'entry',
        );
        self::assertSame('deny', $closed->decision);
        self::assertSame('acs_unavailable_fail_closed', $closed->reasonCode);

        $adapter->forceUnavailable(false);
    }
}
