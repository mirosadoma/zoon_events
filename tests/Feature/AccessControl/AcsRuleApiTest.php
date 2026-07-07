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
final class AcsRuleApiTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_rule_endpoints_return_documented_responses(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['acs.configure']);
        $event = $scan['fixture']['event'];
        $zone = AcsZone::factory()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
        ]);
        $base = "/api/v1/tenant/events/{$event->id}/acs/rules";

        $this->actingAsScanner($scan);

        $create = $this->postJson($base, [
            'zone_id' => $zone->id,
            'ticket_type_id' => $scan['credential']->ticket_type_id,
            'access_direction' => 'entry',
            'anti_passback_exempt' => false,
            'status' => 'active',
        ], $this->acsTenantHeaders($scan, 'acs-rule-create-'.Str::ulid()));

        $create->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id', 'ticket_type_id', 'attendee_type', 'zone_id', 'lane_id',
                    'access_direction', 'anti_passback_exempt', 'valid_from', 'valid_until', 'status',
                ],
            ])
            ->assertJsonPath('data.zone_id', $zone->id);

        $this->getJson($base, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_inverted_time_window_is_rejected(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['acs.configure']);
        $zone = AcsZone::factory()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'event_id' => $scan['fixture']['event']->id,
        ]);
        $base = "/api/v1/tenant/events/{$scan['fixture']['event']->id}/acs/rules";

        $this->actingAsScanner($scan);

        $this->assertProblemDetails(
            $this->postJson($base, [
                'zone_id' => $zone->id,
                'access_direction' => 'entry',
                'valid_from' => now()->addDay()->toIso8601String(),
                'valid_until' => now()->subDay()->toIso8601String(),
            ], $this->acsTenantHeaders($scan, 'acs-rule-window-'.Str::ulid())),
            422,
            'acs_invalid_time_window',
        );
    }
}
