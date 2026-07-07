<?php

namespace Tests\Integration\Security;

use App\Modules\AccessControl\Application\Actions\RegisterAcsIntegrationCredentialAction;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('phase-4-isolation')]
final class Phase4IsolationSweepTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_phase_four_endpoints_reject_cross_scope_access_and_confine_m2m_capabilities(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scanA = $this->createIssuedCredentialScanFixture([
            'acs.configure', 'acs.events.view', 'acs.health.view', 'acs.emergency.manage',
        ]);
        $scanB = $this->createIssuedCredentialScanFixture([
            'acs.configure', 'acs.events.view', 'acs.health.view', 'acs.emergency.manage',
        ]);
        $acsA = $this->createAcsAuthorizationFixture($scanA);
        $acsB = $this->createAcsAuthorizationFixture($scanB);

        $this->actingAsScanner($scanA);
        $headers = fn (string $key): array => $this->acsTenantHeaders($scanA, $key);
        $tenantHeaders = $this->tenantHeaders($scanA['fixture']['tenant']);

        $this->getJson("/api/v1/tenant/events/{$acsB['event']->id}/acs/zones", $tenantHeaders)
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/tenant/events/{$acsB['event']->id}/acs/lanes", $tenantHeaders)
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/tenant/events/{$acsB['event']->id}/acs/rules", $tenantHeaders)
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/tenant/events/{$acsB['event']->id}/acs/gate-events", $tenantHeaders)
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/tenant/events/{$acsB['event']->id}/acs/health", $tenantHeaders)
            ->assertOk()
            ->assertJsonPath('data.lanes', [])
            ->assertJsonPath('data.active_emergency', false);

        $this->postJson(
            "/api/v1/tenant/events/{$acsB['event']->id}/acs/zones",
            ['name' => 'Foreign', 'external_acs_zone_id' => 'iso-zone-'.Str::lower((string) Str::ulid())],
            $headers('iso-zone-'.Str::ulid()),
        )->assertClientError();

        $this->postJson(
            "/api/v1/tenant/events/{$acsA['event']->id}/acs/lanes",
            [
                'zone_id' => $acsB['zone']->id,
                'name' => 'Foreign lane',
                'external_acs_lane_id' => 'iso-lane-'.Str::lower((string) Str::ulid()),
                'gate_type' => 'turnstile',
                'access_direction' => 'entry',
            ],
            $headers('iso-lane-'.Str::ulid()),
        )->assertNotFound();

        // Foreign event registration attempt:
        $this->postJson(
            "/api/v1/tenant/events/{$acsB['event']->id}/acs/integration-credentials",
            ['name' => 'Foreign cred', 'capabilities' => ['authorize']],
            $headers('iso-cred-b-'.Str::ulid()),
        )->assertClientError();

        $this->assertProblemDetails(
            $this->postJson('/api/v1/acs/v1/authorize', [
                'external_acs_lane_id' => $acsB['lane']->external_acs_lane_id,
                'direction' => 'entry',
                'credential_reference' => $acsA['token'],
            ], $this->acsIntegrationHeaders($acsA['secret'], 'iso-auth-'.Str::ulid())),
            404,
            'acs_lane_unmapped',
        );

        $this->assertProblemDetails(
            $this->postJson('/api/v1/acs/v1/events', [
                'external_event_id' => 'iso-event-'.Str::lower((string) Str::ulid()),
                'external_acs_lane_id' => $acsB['lane']->external_acs_lane_id,
                'event_type' => 'entry',
                'occurred_at' => now()->toIso8601String(),
            ], $this->acsIntegrationHeaders($acsA['secret'], 'iso-events-'.Str::ulid())),
            404,
            'acs_event_out_of_scope',
        );

        $this->postJson(
            "/api/v1/tenant/events/{$acsB['event']->id}/acs/emergency",
            ['action' => 'raise', 'zone_id' => $acsB['zone']->id],
            $headers('iso-em-op-'.Str::ulid()),
        )->assertClientError();

        $authorizeOnly = app(RegisterAcsIntegrationCredentialAction::class)->execute(
            $acsA['event']->tenant_id,
            $acsA['event']->id,
            'Authorize only',
            ['authorize'],
        )['secret'];

        $this->assertProblemDetails(
            $this->postJson('/api/v1/acs/v1/events', [
                'external_event_id' => 'cap-event-'.Str::lower((string) Str::ulid()),
                'external_acs_lane_id' => $acsA['lane']->external_acs_lane_id,
                'event_type' => 'entry',
                'occurred_at' => now()->toIso8601String(),
            ], $this->acsIntegrationHeaders($authorizeOnly, 'iso-cap-events-'.Str::ulid())),
            403,
            'acs_capability_denied',
        );

        $this->assertProblemDetails(
            $this->postJson('/api/v1/acs/v1/emergency', [
                'action' => 'raise',
                'external_acs_zone_id' => $acsA['zone']->external_acs_zone_id,
                'occurred_at' => now()->toIso8601String(),
            ], $this->acsIntegrationHeaders($authorizeOnly, 'iso-cap-em-'.Str::ulid())),
            403,
            'acs_capability_denied',
        );

        self::assertInstanceOf(AcsZone::class, AcsZone::query()->find($acsB['zone']->id));
    }
}
