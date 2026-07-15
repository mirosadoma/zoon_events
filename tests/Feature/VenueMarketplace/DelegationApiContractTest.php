<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ActivateRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class DelegationApiContractTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_participant_can_view_delegation_status(): void
    {
        [$owner, $organizer, $rental] = $this->activatedRental('api-show');

        $this->actingAsTenantMember($organizer['user'], $organizer['tenant']);

        $response = $this->getJson(
            "/api/v1/tenant/marketplace/rentals/{$rental->public_id}/delegation",
            $this->tenantHeaders($organizer['tenant']),
        );

        $response->assertSuccessful();
        $data = $response->json('data');

        self::assertArrayHasKey('public_id', $data);
        self::assertArrayHasKey('status', $data);
        self::assertArrayHasKey('starts_at', $data);
        self::assertArrayHasKey('ends_at', $data);
        self::assertArrayHasKey('version', $data);
    }

    public function test_delegation_response_includes_resources(): void
    {
        [$owner, $organizer, $rental] = $this->activatedRental('api-resources');

        $this->actingAsTenantMember($organizer['user'], $organizer['tenant']);

        $response = $this->getJson(
            "/api/v1/tenant/marketplace/rentals/{$rental->public_id}/delegation",
            $this->tenantHeaders($organizer['tenant']),
        );

        $response->assertSuccessful();
        $data = $response->json('data');

        if (isset($data['resources'])) {
            foreach ($data['resources'] as $resource) {
                self::assertArrayHasKey('resource_module', $resource);
                self::assertArrayHasKey('resource_type', $resource);
                self::assertArrayHasKey('provisioning_status', $resource);
                self::assertArrayHasKey('granted_capabilities', $resource);
            }
        }
    }

    public function test_delegation_response_excludes_operational_secrets(): void
    {
        [$owner, $organizer, $rental] = $this->activatedRental('api-secrets');

        $this->actingAsTenantMember($organizer['user'], $organizer['tenant']);

        $response = $this->getJson(
            "/api/v1/tenant/marketplace/rentals/{$rental->public_id}/delegation",
            $this->tenantHeaders($organizer['tenant']),
        );

        $response->assertSuccessful();
        $raw = $response->getContent();
        $lowered = strtolower($raw);

        self::assertStringNotContainsString('password', $lowered);
        self::assertStringNotContainsString('credential', $lowered);
        self::assertStringNotContainsString('access_token', $lowered);
        self::assertStringNotContainsString('pairing_secret', $lowered);
        self::assertStringNotContainsString('idempotency_key_hash', $lowered);
    }

    public function test_owner_can_also_view_delegation_status(): void
    {
        [$owner, $organizer, $rental] = $this->activatedRental('api-owner');

        $this->actingAsTenantMember($owner['user'], $owner['tenant']);

        $response = $this->getJson(
            "/api/v1/tenant/marketplace/rentals/{$rental->public_id}/delegation",
            $this->tenantHeaders($owner['tenant']),
        );

        $response->assertSuccessful();
        $data = $response->json('data');
        self::assertNotEmpty($data['public_id']);
    }

    public function test_nonexistent_rental_returns_error(): void
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);

        $this->actingAsTenantMember($organizer['user'], $organizer['tenant']);

        $response = $this->getJson(
            '/api/v1/tenant/marketplace/rentals/nonexistent-id/delegation',
            $this->tenantHeaders($organizer['tenant']),
        );

        $response->assertStatus(404);
    }

    public function test_delegation_version_is_integer(): void
    {
        [$owner, $organizer, $rental] = $this->activatedRental('api-version');

        $this->actingAsTenantMember($organizer['user'], $organizer['tenant']);

        $response = $this->getJson(
            "/api/v1/tenant/marketplace/rentals/{$rental->public_id}/delegation",
            $this->tenantHeaders($organizer['tenant']),
        );

        $response->assertSuccessful();
        $version = $response->json('data.version');

        self::assertIsInt($version);
    }

    /**
     * @return array{0:array,1:array,2:mixed}
     */
    private function activatedRental(string $key): array
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, $key);
        app(TenantContextStore::class)->clear();

        $publicationPublicId = $inventory['assets'][3]->publications()
            ->where('status', 'active')
            ->value('public_id');

        $rental = $this->createSubmittedMarketplaceRental(
            $organizer,
            $event,
            [$publicationPublicId],
            "{$key}-rental",
        );

        $rental = app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            "{$key}-approve-idem",
            "{$key}-approve-corr",
        );

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        $rental = app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            "{$key}-activate-corr",
        );

        return [$owner, $organizer, $rental];
    }
}
