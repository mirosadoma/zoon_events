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
#[Group('offline-scanning')]
final class OfflineScanApiTest extends Phase2MySqlTestCase
{
    use AssertsProblemDetails;
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_offline_scan_routes_match_contract(): void
    {
        $allowlist = collect(app('router')->getRoutes()->getRoutes())->first(
            fn (Route $route): bool => $route->uri() === 'api/v1/tenant/events/{event_id}/offline-allowlist'
                && in_array('GET', $route->methods(), true),
        );
        $batch = collect(app('router')->getRoutes()->getRoutes())->first(
            fn (Route $route): bool => $route->uri() === 'api/v1/tenant/events/{event_id}/offline-scan-batches'
                && in_array('POST', $route->methods(), true),
        );

        self::assertNotNull($allowlist);
        self::assertNotNull($batch);
        self::assertContains('permission:checkin.scan.submit,tenant', $allowlist->gatherMiddleware());
        self::assertContains('permission:checkin.scan.submit,tenant', $batch->gatherMiddleware());
        self::assertContains('idempotency', $batch->gatherMiddleware());
    }

    public function test_offline_scan_endpoints_return_documented_problem_responses(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $eventId = $scan['fixture']['event']->id;
        $allowlistUrl = "/api/v1/tenant/events/{$eventId}/offline-allowlist";
        $batchUrl = "/api/v1/tenant/events/{$eventId}/offline-scan-batches";

        $this->getJson($allowlistUrl, $this->tenantHeaders($scan['fixture']['tenant']))->assertUnauthorized();
        $this->postJson($batchUrl, [
            'device_reference' => 'device-1',
            'scans' => [['qr_payload' => $scan['token'], 'scanned_at' => now()->toIso8601String()]],
        ], $this->scanHeaders($scan, 'offline-batch-unauth'))->assertUnauthorized();

        $outsider = User::factory()->create();
        TenantMembership::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'user_id' => $outsider->id,
            'status' => 'active',
            'created_by_user_id' => $scan['fixture']['actor']->id,
        ]);
        $this->actingAsTenantMember($outsider, $scan['fixture']['tenant']);
        $this->getJson($allowlistUrl, $this->tenantHeaders($scan['fixture']['tenant']))->assertForbidden();

        $this->actingAsScanner($scan);
        $this->getJson($allowlistUrl, $this->tenantHeaders($scan['fixture']['tenant']))->assertOk();

        $this->actingAsScanner($scan);
        $this->postJson($batchUrl, [
            'device_reference' => 'device-1',
            'scans' => [['qr_payload' => $scan['token'], 'scanned_at' => now()->toIso8601String(), 'forged' => true]],
        ], $this->scanHeaders($scan, 'offline-batch-422-key'))->assertUnprocessable();

        $this->actingAsScanner($scan);
        $this->postJson($batchUrl, [
            'device_reference' => 'device-2',
            'scans' => [['qr_payload' => $scan['token'], 'scanned_at' => now()->addSecond()->toIso8601String()]],
        ], $this->scanHeaders($scan, 'offline-batch-202-key'))->assertAccepted()
            ->assertJsonPath('data.status', 'processed');
    }
}
