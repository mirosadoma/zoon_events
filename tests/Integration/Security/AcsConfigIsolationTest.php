<?php

namespace Tests\Integration\Security;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
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
#[Group('acs-config')]
final class AcsConfigIsolationTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_cross_tenant_event_config_requests_match_unknown_target_responses(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scanA = $this->createIssuedCredentialScanFixture(['acs.configure']);
        $scanB = $this->createIssuedCredentialScanFixture(['acs.configure']);
        $foreignZone = AcsZone::factory()->create([
            'tenant_id' => $scanB['fixture']['tenant']->id,
            'event_id' => $scanB['fixture']['event']->id,
        ]);
        $foreignLane = AcsLane::factory()->create([
            'tenant_id' => $scanB['fixture']['tenant']->id,
            'event_id' => $scanB['fixture']['event']->id,
            'zone_id' => $foreignZone->id,
        ]);

        $this->actingAsScanner($scanA);

        $this->getJson(
            "/api/v1/tenant/events/{$scanB['fixture']['event']->id}/acs/zones",
            $this->tenantHeaders($scanA['fixture']['tenant']),
        )->assertOk()->assertJsonCount(0, 'data');

        $this->postJson(
            "/api/v1/tenant/events/{$scanB['fixture']['event']->id}/acs/zones",
            [
                'name' => 'Foreign zone',
                'external_acs_zone_id' => 'foreign-zone-'.Str::lower((string) Str::ulid()),
            ],
            $this->acsTenantHeaders($scanA, 'acs-iso-zone-'.Str::ulid()),
        )->assertClientError();

        $this->patchJson(
            "/api/v1/tenant/events/{$scanA['fixture']['event']->id}/acs/zones/{$foreignZone->id}",
            ['name' => 'Should not update'],
            $this->acsTenantHeaders($scanA, 'acs-iso-zone-patch-'.Str::ulid()),
        )->assertNotFound();

        $this->postJson(
            "/api/v1/tenant/events/{$scanA['fixture']['event']->id}/acs/lanes",
            [
                'zone_id' => $foreignZone->id,
                'name' => 'Foreign lane',
                'external_acs_lane_id' => 'foreign-lane-'.Str::lower((string) Str::ulid()),
                'gate_type' => 'turnstile',
                'access_direction' => 'entry',
            ],
            $this->acsTenantHeaders($scanA, 'acs-iso-lane-'.Str::ulid()),
        )->assertNotFound();

        $this->postJson(
            "/api/v1/tenant/events/{$scanA['fixture']['event']->id}/acs/rules",
            [
                'zone_id' => $foreignZone->id,
                'access_direction' => 'entry',
            ],
            $this->acsTenantHeaders($scanA, 'acs-iso-rule-'.Str::ulid()),
        )->assertNotFound();

        $this->postJson(
            "/api/v1/tenant/events/{$scanB['fixture']['event']->id}/acs/integration-credentials",
            [
                'name' => 'Foreign credential',
                'capabilities' => ['authorize'],
            ],
            $this->acsTenantHeaders($scanA, 'acs-iso-cred-'.Str::ulid()),
        )->assertClientError();

        self::assertSame($foreignZone->name, AcsZone::query()->findOrFail($foreignZone->id)->name);
        self::assertSame($foreignLane->name, AcsLane::query()->findOrFail($foreignLane->id)->name);
    }
}
