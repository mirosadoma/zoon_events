<?php

namespace Tests\Feature\AccessControl;

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
#[Group('acs-config')]
final class AcsZoneApiTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_zone_endpoints_return_documented_responses(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['acs.configure']);
        $event = $scan['fixture']['event'];
        $base = "/api/v1/tenant/events/{$event->id}/acs/zones";

        $this->postJson($base, [
            'name' => 'Main Hall',
            'external_acs_zone_id' => 'zone-main-'.Str::lower((string) Str::ulid()),
        ])->assertUnauthorized();

        $this->actingAsScanner($scan);
        $this->assertProblemDetails(
            $this->postJson($base, [
                'name' => 'Main Hall',
                'external_acs_zone_id' => 'zone-main-'.Str::lower((string) Str::ulid()),
                'forged_field' => true,
            ], $this->acsTenantHeaders($scan, 'acs-zone-422-'.Str::ulid())),
            422,
            'validation_failed',
        );

        $create = $this->postJson($base, [
            'name' => 'Main Hall',
            'external_acs_zone_id' => 'zone-main-'.Str::lower((string) Str::ulid()),
            'anti_passback_enabled' => true,
            'unavailability_mode' => 'fail_closed',
            'emergency_egress_mode' => 'fail_open',
            'status' => 'active',
        ], $this->acsTenantHeaders($scan, 'acs-zone-create-'.Str::ulid()));

        $create->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'external_acs_zone_id', 'anti_passback_enabled',
                    'unavailability_mode', 'emergency_egress_mode', 'status',
                ],
            ])
            ->assertJsonPath('data.name', 'Main Hall');

        $zoneId = $create->json('data.id');
        $externalId = $create->json('data.external_acs_zone_id');

        $this->assertProblemDetails(
            $this->postJson($base, [
                'name' => 'Duplicate',
                'external_acs_zone_id' => $externalId,
            ], $this->acsTenantHeaders($scan, 'acs-zone-dup-'.Str::ulid())),
            409,
            'acs_duplicate_external_id',
        );

        $this->getJson($base, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->patchJson(
            "{$base}/{$zoneId}",
            ['name' => 'Main Hall Updated', 'status' => 'inactive'],
            $this->acsTenantHeaders($scan, 'acs-zone-update-'.Str::ulid()),
        )->assertOk()
            ->assertJsonPath('data.name', 'Main Hall Updated')
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_zone_mutations_require_acs_configure_permission(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $zone = AcsZone::factory()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'event_id' => $scan['fixture']['event']->id,
        ]);
        $base = "/api/v1/tenant/events/{$scan['fixture']['event']->id}/acs/zones";

        $this->actingAsScanner($scan);

        $this->assertProblemDetails(
            $this->postJson($base, [
                'name' => 'Denied',
                'external_acs_zone_id' => 'zone-denied-'.Str::lower((string) Str::ulid()),
            ], $this->acsTenantHeaders($scan, 'acs-zone-forbidden-'.Str::ulid())),
            403,
            'acs_config_not_permitted',
        );

        $this->assertProblemDetails(
            $this->getJson($base, $this->tenantHeaders($scan['fixture']['tenant'])),
            403,
            'acs_config_not_permitted',
        );

        $this->assertProblemDetails(
            $this->patchJson(
                "{$base}/{$zone->id}",
                ['name' => 'Denied'],
                $this->acsTenantHeaders($scan, 'acs-zone-patch-forbidden-'.Str::ulid()),
            ),
            403,
            'acs_config_not_permitted',
        );
    }
}
