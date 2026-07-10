<?php

namespace Tests\Contract\Phase4;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-config')]
final class AcsConfigApiTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_acs_config_routes_match_contract(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        $this->assertRoute($routes, 'api/v1/tenant/events/{event_id}/acs/zones', 'POST', ['idempotency']);
        $this->assertRoute($routes, 'api/v1/tenant/events/{event_id}/acs/zones', 'GET');
        $this->assertRoute($routes, 'api/v1/tenant/events/{event_id}/acs/zones/{zone_id}', 'PATCH', ['idempotency']);
        $this->assertRoute($routes, 'api/v1/tenant/events/{event_id}/acs/lanes', 'POST', ['idempotency']);
        $this->assertRoute($routes, 'api/v1/tenant/events/{event_id}/acs/lanes', 'GET');
        $this->assertRoute($routes, 'api/v1/tenant/events/{event_id}/acs/rules', 'POST', ['idempotency']);
        $this->assertRoute($routes, 'api/v1/tenant/events/{event_id}/acs/rules', 'GET');
        $this->assertRoute($routes, 'api/v1/tenant/events/{event_id}/acs/integration-credentials', 'POST', ['idempotency']);
    }

    public function test_acs_config_contract_cases(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['acs.configure']);
        $event = $scan['fixture']['event'];
        $zonesUrl = "/api/v1/tenant/events/{$event->id}/acs/zones";
        $lanesUrl = "/api/v1/tenant/events/{$event->id}/acs/lanes";
        $rulesUrl = "/api/v1/tenant/events/{$event->id}/acs/rules";
        $credUrl = "/api/v1/tenant/events/{$event->id}/acs/integration-credentials";

        $this->postJson($zonesUrl, [
            'name' => 'Zone',
            'external_acs_zone_id' => 'zone-contract-'.Str::lower((string) Str::ulid()),
        ])->assertUnauthorized();

        $this->actingAsScanner($scan);

        $this->assertProblemDetails(
            $this->postJson($zonesUrl, [
                'name' => 'Zone',
                'external_acs_zone_id' => 'zone-contract-'.Str::lower((string) Str::ulid()),
                'forged_field' => true,
            ], $this->acsTenantHeaders($scan, 'acs-config-zone-422-'.Str::ulid())),
            422,
            'validation_failed',
        );

        $zone = $this->postJson($zonesUrl, [
            'name' => 'Zone',
            'external_acs_zone_id' => 'zone-contract-'.Str::lower((string) Str::ulid()),
        ], $this->acsTenantHeaders($scan, 'acs-config-zone-201-'.Str::ulid()))
            ->assertCreated()
            ->json('data');

        $this->getJson($zonesUrl, $this->tenantHeaders($scan['fixture']['tenant']))->assertOk();

        $this->patchJson(
            "{$zonesUrl}/{$zone['id']}",
            ['name' => 'Updated Zone'],
            $this->acsTenantHeaders($scan, 'acs-config-zone-patch-'.Str::ulid()),
        )->assertOk();

        $this->postJson($lanesUrl, [
            'zone_id' => $zone['id'],
            'name' => 'Lane',
            'external_acs_lane_id' => 'lane-contract-'.Str::lower((string) Str::ulid()),
            'gate_type' => 'turnstile',
            'access_direction' => 'entry',
        ], $this->acsTenantHeaders($scan, 'acs-config-lane-201-'.Str::ulid()))->assertCreated();

        $this->getJson($lanesUrl, $this->tenantHeaders($scan['fixture']['tenant']))->assertOk();

        $this->assertProblemDetails(
            $this->postJson($rulesUrl, [
                'zone_id' => $zone['id'],
                'access_direction' => 'entry',
                'valid_from' => now()->addDay()->toIso8601String(),
                'valid_until' => now()->subDay()->toIso8601String(),
            ], $this->acsTenantHeaders($scan, 'acs-config-rule-422-'.Str::ulid())),
            422,
            'acs_invalid_time_window',
        );

        $this->postJson($rulesUrl, [
            'zone_id' => $zone['id'],
            'ticket_type_id' => $scan['credential']->ticket_type_id,
            'access_direction' => 'entry',
        ], $this->acsTenantHeaders($scan, 'acs-config-rule-201-'.Str::ulid()))->assertCreated();

        $this->getJson($rulesUrl, $this->tenantHeaders($scan['fixture']['tenant']))->assertOk();

        $duplicateExternal = $zone['external_acs_zone_id'];
        $this->assertProblemDetails(
            $this->postJson($zonesUrl, [
                'name' => 'Duplicate',
                'external_acs_zone_id' => $duplicateExternal,
            ], $this->acsTenantHeaders($scan, 'acs-config-zone-409-'.Str::ulid())),
            409,
            'acs_duplicate_external_id',
        );

        $this->postJson($credUrl, [
            'name' => 'Integration',
            'capabilities' => ['authorize'],
        ], $this->acsTenantHeaders($scan, 'acs-config-cred-201-'.Str::ulid()))->assertCreated();

        $this->patchJson(
            "{$zonesUrl}/01UNKNOWNZONE00000000000000",
            ['name' => 'Missing'],
            $this->acsTenantHeaders($scan, 'acs-config-zone-404-'.Str::ulid()),
        )->assertNotFound();
    }

    /** @param Collection<int, Route> $routes */
    private function assertRoute(Collection $routes, string $uri, string $method, array $middleware = []): void
    {
        $route = $routes->first(
            fn (Route $route): bool => $route->uri() === $uri && in_array($method, $route->methods(), true),
        );

        self::assertNotNull($route, "Missing route {$method} {$uri}");

        foreach ($middleware as $name) {
            self::assertContains($name, $route->gatherMiddleware(), "Expected middleware {$name} on {$uri}");
        }
    }
}
