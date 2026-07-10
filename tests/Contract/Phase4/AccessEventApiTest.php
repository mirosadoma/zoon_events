<?php

namespace Tests\Contract\Phase4;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-events')]
final class AccessEventApiTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_ingest_access_event_route_matches_contract(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(
            fn (Route $route): bool => $route->uri() === 'api/v1/acs/v1/events'
                && in_array('POST', $route->methods(), true),
        );

        self::assertNotNull($route);
        self::assertContains('acs.capability:event.ingest', $route->gatherMiddleware());
        self::assertContains('idempotency', $route->gatherMiddleware());
    }

    public function test_ingest_access_event_contract_cases(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $url = '/api/v1/acs/v1/events';

        $this->postJson($url, [
            'external_event_id' => 'contract-event-'.Str::lower((string) Str::ulid()),
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'event_type' => 'entry',
            'occurred_at' => now()->toIso8601String(),
        ])->assertUnauthorized();

        $this->assertProblemDetails(
            $this->postJson($url, [
                'external_event_id' => 'contract-event-'.Str::lower((string) Str::ulid()),
                'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
                'event_type' => 'entry',
                'occurred_at' => now()->toIso8601String(),
                'forged_field' => true,
            ], $this->acsIntegrationHeaders($acs['secret'], 'acs-event-contract-422-'.Str::ulid())),
            422,
            'validation_failed',
        );

        $this->assertProblemDetails(
            $this->postJson($url, [
                'external_event_id' => 'contract-event-'.Str::lower((string) Str::ulid()),
                'external_acs_lane_id' => 'missing-lane',
                'event_type' => 'entry',
                'occurred_at' => now()->toIso8601String(),
            ], $this->acsIntegrationHeaders($acs['secret'], 'acs-event-contract-404-'.Str::ulid())),
            404,
            'acs_event_out_of_scope',
        );

        $this->postJson($url, [
            'external_event_id' => 'contract-event-'.Str::lower((string) Str::ulid()),
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'event_type' => 'exit',
            'occurred_at' => now()->toIso8601String(),
            'credential_reference' => $acs['token'],
        ], $this->acsIntegrationHeaders($acs['secret'], 'acs-event-contract-202-'.Str::ulid()))
            ->assertAccepted()
            ->assertJsonStructure(['data' => ['id', 'event_type', 'direction', 'occurred_at']]);
    }
}
