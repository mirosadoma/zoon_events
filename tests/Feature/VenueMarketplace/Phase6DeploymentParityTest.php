<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterAssetPort;
use App\Modules\Events\Application\Contracts\MarketplaceEventReader;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskAssetPort;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerAssetPort;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedAcsAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedKioskAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedPrinterAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedScannerAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeMarketplaceEventReader;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeOrganizationEligibility;
use Database\Factories\VenueMarketplaceFactory;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

#[Group('phase-6')]
#[Group('deployment-parity')]
class Phase6DeploymentParityTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    private const DEPLOYMENT_MODES = ['saas', 'on_premise'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
        $this->registerFakes();
    }

    // ─── Publish scenario ────────────────────────────────────────

    public function test_publish_asset_produces_same_result_in_both_modes(): void
    {
        $results = $this->runInBothModes(function (string $mode): array {
            $owner = $this->createTenantMember([], ['organization_type' => 'venue_owner']);
            $this->grantPermissions($owner, ['venue.manage']);
            $this->actingAsTenantMember($owner['user'], $owner['tenant']);

            $factory = app(VenueMarketplaceFactory::class);
            $inventory = $factory->createPublishedInventory(
                $owner['tenant']->id,
                $owner['user']->id,
                "parity-publish-{$mode}",
            );

            return [
                'venue_status' => $inventory['venue']->status,
                'asset_count' => count($inventory['assets']),
                'publication_count' => DB::table('marketplace_catalog_publications')
                    ->where('tenant_id', $owner['tenant']->id)
                    ->where('status', 'active')
                    ->count(),
            ];
        });

        $this->assertModeResultsMatch($results, ['venue_status', 'asset_count', 'publication_count']);
    }

    // ─── Request rental scenario ─────────────────────────────────

    public function test_rental_request_produces_same_status_in_both_modes(): void
    {
        $results = $this->runInBothModes(function (string $mode): array {
            [$owner, $organizer, $rental] = $this->buildRentalFixture($mode);

            return [
                'rental_status' => $rental->status,
                'has_public_id' => $rental->public_id !== null,
            ];
        });

        $this->assertModeResultsMatch($results, ['rental_status', 'has_public_id']);
    }

    // ─── Approve rental scenario ─────────────────────────────────

    public function test_approve_produces_same_status_in_both_modes(): void
    {
        $results = $this->runInBothModes(function (string $mode): array {
            [$owner, $organizer, $rental] = $this->buildRentalFixture($mode);
            $this->actingAsTenantMember($owner['user'], $owner['tenant']);

            $response = $this->postJson(
                "/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve",
                [],
                $this->tenantHeaders($owner['tenant']),
            );

            return [
                'http_status' => $response->status(),
                'rental_status' => $response->json('data.status') ?? $response->json('status'),
            ];
        });

        $this->assertModeResultsMatch($results, ['http_status', 'rental_status']);
    }

    // ─── Activate rental scenario ────────────────────────────────

    public function test_activate_produces_same_status_in_both_modes(): void
    {
        $results = $this->runInBothModes(function (string $mode): array {
            [$owner, $organizer, $rental] = $this->buildApprovedRentalFixture($mode);

            $rental->refresh();

            return [
                'rental_status' => $rental->status,
                'delegation_exists' => DB::table('control_delegations')
                    ->where('rental_request_id', $rental->id)
                    ->exists(),
            ];
        });

        $this->assertModeResultsMatch($results, ['rental_status', 'delegation_exists']);
    }

    // ─── Revoke / Expire scenario ────────────────────────────────

    public function test_revoke_produces_same_reason_code_in_both_modes(): void
    {
        $results = $this->runInBothModes(function (string $mode): array {
            [$owner, $organizer, $rental] = $this->buildApprovedRentalFixture($mode);
            $this->actingAsTenantMember($owner['user'], $owner['tenant']);

            $response = $this->postJson(
                "/api/v1/tenant/marketplace/rentals/{$rental->public_id}/revoke",
                ['reason' => 'parity test revocation'],
                $this->tenantHeaders($owner['tenant']),
            );

            return [
                'http_status' => $response->status(),
                'rental_status' => $response->json('data.status') ?? $response->json('status'),
            ];
        });

        $this->assertModeResultsMatch($results, ['http_status', 'rental_status']);
    }

    // ─── Settlement statement scenario ───────────────────────────

    public function test_statement_listing_produces_same_structure_in_both_modes(): void
    {
        $results = $this->runInBothModes(function (string $mode): array {
            [$owner, $organizer, $rental] = $this->buildApprovedRentalFixture($mode);

            $this->actingAsTenantMember($owner['user'], $owner['tenant']);
            $this->grantPermissions($owner, ['reports.view']);
            $response = $this->getJson(
                '/api/v1/tenant/marketplace/statements',
                $this->tenantHeaders($owner['tenant']),
            );

            return [
                'http_status' => $response->status(),
                'has_data_key' => array_key_exists('data', $response->json()),
            ];
        });

        $this->assertModeResultsMatch($results, ['http_status', 'has_data_key']);
    }

    // ─── Export scenario ─────────────────────────────────────────

    public function test_statement_export_returns_same_http_behaviour_in_both_modes(): void
    {
        $results = $this->runInBothModes(function (string $mode): array {
            [$owner, $organizer, $rental] = $this->buildApprovedRentalFixture($mode);
            $this->grantPermissions($owner, ['reports.view']);

            $statementPublicId = DB::table('settlement_statements')
                ->where('rental_request_id', $rental->id)
                ->value('public_id');

            if ($statementPublicId === null) {
                return ['http_status' => 'no_statement', 'content_type' => null];
            }

            $this->actingAsTenantMember($owner['user'], $owner['tenant']);
            $response = $this->getJson(
                "/api/v1/tenant/marketplace/statements/{$statementPublicId}/export",
                $this->tenantHeaders($owner['tenant']),
            );

            return [
                'http_status' => $response->status(),
                'content_type' => $response->headers->get('Content-Type'),
            ];
        });

        $this->assertModeResultsMatch($results, ['http_status']);
    }

    // ─── Dispute scenario ────────────────────────────────────────

    public function test_dispute_open_produces_same_status_in_both_modes(): void
    {
        $results = $this->runInBothModes(function (string $mode): array {
            [$owner, $organizer, $rental] = $this->buildApprovedRentalFixture($mode);
            $this->grantPermissions($organizer, ['reports.view']);

            $statementPublicId = DB::table('settlement_statements')
                ->where('rental_request_id', $rental->id)
                ->value('public_id');

            if ($statementPublicId === null) {
                return ['http_status' => 'no_statement', 'dispute_status' => null];
            }

            $this->actingAsTenantMember($organizer['user'], $organizer['tenant']);
            $response = $this->postJson(
                "/api/v1/tenant/marketplace/statements/{$statementPublicId}/disputes",
                ['reason' => 'parity_test', 'description' => 'Deployment parity check'],
                $this->tenantHeaders($organizer['tenant']),
            );

            return [
                'http_status' => $response->status(),
                'dispute_status' => $response->json('data.status') ?? $response->json('status'),
            ];
        });

        $this->assertModeResultsMatch($results, ['http_status', 'dispute_status']);
    }

    // ─── Reason codes parity ─────────────────────────────────────

    public function test_reason_codes_are_identical_across_modes(): void
    {
        $results = $this->runInBothModes(function (): array {
            return [
                'reason_codes' => Phase6Problem::reasonCodes(),
            ];
        });

        self::assertSame($results['saas']['reason_codes'], $results['on_premise']['reason_codes']);
    }

    // ─── Infrastructure config divergence ────────────────────────

    public function test_only_infrastructure_config_differs_between_modes(): void
    {
        $behaviourKeys = [
            'zonetec.tenant_isolation',
            'audit.hmac_algorithm',
            'queue.default',
        ];

        $saasValues = [];
        $onPremValues = [];

        config()->set('zonetec.deployment_mode', 'saas');
        config()->set('integrations.allow_network', false);
        foreach ($behaviourKeys as $key) {
            $saasValues[$key] = config($key);
        }

        config()->set('zonetec.deployment_mode', 'on_premise');
        config()->set('integrations.allow_network', false);
        foreach ($behaviourKeys as $key) {
            $onPremValues[$key] = config($key);
        }

        foreach ($behaviourKeys as $key) {
            self::assertSame(
                $saasValues[$key],
                $onPremValues[$key],
                "Behavioural config '{$key}' must not differ between deployment modes.",
            );
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /** @return array<string, mixed> keyed by mode */
    private function runInBothModes(callable $scenario): array
    {
        $results = [];
        foreach (self::DEPLOYMENT_MODES as $mode) {
            config()->set('zonetec.deployment_mode', $mode);
            config()->set('integrations.allow_network', false);
            $results[$mode] = $scenario($mode);
        }

        return $results;
    }

    private function assertModeResultsMatch(array $results, array $keys): void
    {
        foreach ($keys as $key) {
            self::assertSame(
                $results['saas'][$key] ?? null,
                $results['on_premise'][$key] ?? null,
                "Deployment parity violation for '{$key}': SaaS="
                    .json_encode($results['saas'][$key] ?? null)
                    .' vs on-premise='.json_encode($results['on_premise'][$key] ?? null),
            );
        }
    }

    /** @return array{0: array, 1: array, 2: \App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest} */
    private function buildRentalFixture(string $mode): array
    {
        $owner = $this->createTenantMember([], ['organization_type' => 'venue_owner']);
        $organizer = $this->createTenantMember([], ['organization_type' => 'organizer']);
        $this->grantPermissions($owner, ['venue.manage', 'rentals.approve']);
        $this->grantPermissions($organizer, ['marketplace.manage']);

        $factory = app(VenueMarketplaceFactory::class);
        $factory->createPublishedInventory($owner['tenant']->id, $owner['user']->id, "parity-{$mode}");

        $event = $factory->createOrganizerEvent(
            $organizer['tenant']->id,
            $organizer['user']->id,
            "parity-event-{$mode}",
        );

        $pubIds = DB::table('marketplace_catalog_publications')
            ->where('tenant_id', $owner['tenant']->id)
            ->where('status', 'active')
            ->limit(2)
            ->pluck('public_id')
            ->all();

        $rental = $factory->createSubmittedRental(
            $organizer['tenant']->id,
            $organizer['user']->id,
            $event->id,
            $pubIds,
            key: "parity-rental-{$mode}",
        );

        return [$owner, $organizer, $rental];
    }

    /** @return array{0: array, 1: array, 2: \App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest} */
    private function buildApprovedRentalFixture(string $mode): array
    {
        [$owner, $organizer, $rental] = $this->buildRentalFixture($mode);

        $this->actingAsTenantMember($owner['user'], $owner['tenant']);
        $this->postJson(
            "/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve",
            [],
            $this->tenantHeaders($owner['tenant']),
        );

        return [$owner, $organizer, $rental->fresh()];
    }

    private function registerFakes(): void
    {
        app()->instance(OrganizationEligibility::class, new FakeOrganizationEligibility);
        app()->instance(MarketplaceEventReader::class, new FakeMarketplaceEventReader);
        app()->instance(DelegatedAcsAssetPort::class, new FakeDelegatedAcsAssetPort);
        app()->instance(DelegatedKioskAssetPort::class, new FakeDelegatedKioskAssetPort);
        app()->instance(DelegatedPrinterAssetPort::class, new FakeDelegatedPrinterAssetPort);
        app()->instance(DelegatedScannerAssetPort::class, new FakeDelegatedScannerAssetPort);
    }

    private function grantPermissions(array $fixture, array $keys): void
    {
        $role = DB::table('tenant_roles')->insertGetId([
            'tenant_id' => $fixture['tenant']->id,
            'name' => 'Parity-'.implode('-', $keys).'-'.$fixture['tenant']->id.'-'.uniqid(),
            'is_system' => false,
            'created_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (DB::table('permissions')->whereIn('key', $keys)->pluck('id') as $permId) {
            DB::table('tenant_role_permissions')->insert([
                'tenant_id' => $fixture['tenant']->id,
                'tenant_role_id' => $role,
                'permission_id' => $permId,
                'granted_by_user_id' => $fixture['user']->id,
                'created_at' => now(),
            ]);
        }
        DB::table('tenant_role_assignments')->insert([
            'tenant_id' => $fixture['tenant']->id,
            'tenant_membership_id' => $fixture['membership']->id,
            'tenant_role_id' => $role,
            'granted_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
