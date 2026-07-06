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
final class SubmitScanApiTest extends Phase2MySqlTestCase
{
    use AssertsProblemDetails;
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_submit_scan_route_matches_contract(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(
            fn (Route $route): bool => $route->uri() === 'api/v1/tenant/events/{event_id}/scans'
                && in_array('POST', $route->methods(), true),
        );

        self::assertNotNull($route);
        self::assertContains('permission:checkin.scan.submit,tenant', $route->gatherMiddleware());
        self::assertContains('tenant.context', $route->gatherMiddleware());
    }

    public function test_submit_scan_returns_documented_problem_responses(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $eventId = $scan['fixture']['event']->id;
        $url = "/api/v1/tenant/events/{$eventId}/scans";

        $this->postJson($url, [
            'qr_payload' => $scan['token'],
            'scanner_type' => 'staff_phone',
        ], $this->scanHeaders($scan))->assertUnauthorized();

        $this->actingAsScanner($scan);
        $this->postJson($url, [
            'qr_payload' => $scan['token'],
            'scanner_type' => 'staff_phone',
            'forged_field' => true,
        ], $this->scanHeaders($scan, 'scan-contract-422'))->assertUnprocessable();

        $this->actingAsScanner($scan);
        $this->postJson($url, [
            'qr_payload' => $scan['token'],
            'scanner_type' => 'staff_phone',
        ], $this->scanHeaders($scan, 'scan-contract-200'))->assertOk()
            ->assertJsonPath('data.result', 'accepted');

        $outsider = User::factory()->create();
        TenantMembership::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'user_id' => $outsider->id,
            'status' => 'active',
            'created_by_user_id' => $scan['fixture']['actor']->id,
        ]);
        $this->actingAsTenantMember($outsider, $scan['fixture']['tenant']);
        $this->postJson($url, [
            'qr_payload' => $scan['token'],
            'scanner_type' => 'staff_phone',
        ], $this->scanHeaders($scan, 'scan-contract-403'))->assertForbidden();
    }
}
