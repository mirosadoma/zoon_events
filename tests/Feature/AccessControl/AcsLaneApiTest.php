<?php

namespace Tests\Feature\AccessControl;

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
#[Group('acs-config')]
final class AcsLaneApiTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_lane_endpoints_return_documented_responses(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['acs.configure']);
        $event = $scan['fixture']['event'];
        $zone = AcsZone::factory()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
        ]);
        $base = "/api/v1/tenant/events/{$event->id}/acs/lanes";

        $this->actingAsScanner($scan);

        $create = $this->postJson($base, [
            'zone_id' => $zone->id,
            'name' => 'Gate A',
            'external_acs_lane_id' => 'lane-a-'.Str::lower((string) Str::ulid()),
            'gate_type' => 'turnstile',
            'access_direction' => 'entry',
            'is_admission_lane' => false,
            'status' => 'active',
        ], $this->acsTenantHeaders($scan, 'acs-lane-create-'.Str::ulid()));

        $create->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id', 'zone_id', 'name', 'external_acs_lane_id', 'gate_type',
                    'access_direction', 'is_admission_lane', 'status', 'health_status', 'last_seen_at',
                ],
            ])
            ->assertJsonPath('data.zone_id', $zone->id);

        $externalLaneId = $create->json('data.external_acs_lane_id');

        $this->assertProblemDetails(
            $this->postJson($base, [
                'zone_id' => $zone->id,
                'name' => 'Duplicate',
                'external_acs_lane_id' => $externalLaneId,
                'gate_type' => 'door',
                'access_direction' => 'exit',
            ], $this->acsTenantHeaders($scan, 'acs-lane-dup-'.Str::ulid())),
            409,
            'acs_duplicate_external_id',
        );

        $this->getJson($base, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_lane_referencing_a_foreign_event_zone_is_rejected_as_unknown(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['acs.configure']);
        $otherScan = $this->createIssuedCredentialScanFixture(['acs.configure']);
        $foreignZone = AcsZone::factory()->create([
            'tenant_id' => $otherScan['fixture']['tenant']->id,
            'event_id' => $otherScan['fixture']['event']->id,
        ]);
        $base = "/api/v1/tenant/events/{$scan['fixture']['event']->id}/acs/lanes";

        $this->actingAsScanner($scan);

        $this->postJson($base, [
            'zone_id' => $foreignZone->id,
            'name' => 'Cross event lane',
            'external_acs_lane_id' => 'lane-cross-'.Str::lower((string) Str::ulid()),
            'gate_type' => 'turnstile',
            'access_direction' => 'entry',
        ], $this->acsTenantHeaders($scan, 'acs-lane-cross-'.Str::ulid()))
            ->assertNotFound();

        self::assertSame(0, AcsLane::query()->where('external_acs_lane_id', 'like', 'lane-cross-%')->count());
    }
}
