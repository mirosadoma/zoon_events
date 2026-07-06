<?php

namespace Tests\Contract\Phase2;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
final class CheckInSummaryApiTest extends Phase2MySqlTestCase
{
    use AssertsProblemDetails;
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_check_in_summary_route_matches_contract(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(
            fn (Route $route): bool => $route->uri() === 'api/v1/tenant/events/{event_id}/check-in-summary'
                && in_array('GET', $route->methods(), true),
        );

        self::assertNotNull($route);
        self::assertContains('permission:checkin.dashboard.view,tenant', $route->gatherMiddleware());
        self::assertContains('tenant.context', $route->gatherMiddleware());
    }

    public function test_check_in_summary_returns_documented_problem_responses(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.dashboard.view']);
        $eventId = $scan['fixture']['event']->id;
        $url = "/api/v1/tenant/events/{$eventId}/check-in-summary";

        $this->getJson($url, $this->tenantHeaders($scan['fixture']['tenant']))->assertUnauthorized();

        $outsider = User::factory()->create();
        TenantMembership::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'user_id' => $outsider->id,
            'status' => 'active',
            'created_by_user_id' => $scan['fixture']['actor']->id,
        ]);
        $this->actingAsTenantMember($outsider, $scan['fixture']['tenant']);
        $this->getJson($url, $this->tenantHeaders($scan['fixture']['tenant']))->assertForbidden();

        $this->grantTenantPermissions($scan['fixture']['tenant'], $scan['membership'], ['checkin.dashboard.view']);
        $this->actingAsScanner($scan);
        $this->getJson($url, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'registered_count',
                    'checked_in_count',
                    'rejected_count',
                    'duplicate_count',
                    'last_scan_at',
                ],
            ]);

        $this->actingAsScanner($scan);
        $this->getJson('/api/v1/tenant/events/01UNKNOWNEVENT0000000000/check-in-summary', $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertNotFound();
    }
}
