<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ArchiveVenueAction;
use App\Modules\VenueMarketplace\Application\Actions\ChangeVenueStatusAction;
use App\Modules\VenueMarketplace\Application\Actions\CreateVenueAction;
use App\Modules\VenueMarketplace\Application\Actions\CreateVenueAssetAction;
use App\Modules\VenueMarketplace\Application\Actions\PublishVenueAssetAction;
use App\Modules\VenueMarketplace\Application\Actions\ReplaceAssetAvailabilityAction;
use App\Modules\VenueMarketplace\Application\Actions\RetireVenueAssetAction;
use App\Modules\VenueMarketplace\Application\Actions\UpdateVenueAction;
use App\Modules\VenueMarketplace\Application\Actions\UpdateVenueAssetAction;
use App\Modules\VenueMarketplace\Application\Actions\WithdrawVenueAssetPublicationAction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Services\MarketplaceCapabilityRegistry;
use App\Modules\VenueMarketplace\Domain\Services\PublicationReadinessPolicy;
use App\Modules\VenueMarketplace\Domain\Services\VenueAssetBindingPolicy;
use App\Modules\VenueMarketplace\Domain\Services\VenueLifecyclePolicy;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\Venue;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeOrganizationEligibility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

class OwnerVenueAuditTest extends TestCase
{
    use BuildsTenantFixtures;
    use DatabaseTransactions;

    public function test_every_owner_catalog_mutation_writes_sanitized_correlated_audit_evidence(): void
    {
        [$fixture, $writer, $actions] = $this->fixture();
        $tenantId = (int) $fixture['tenant']->id;
        $actorId = (int) $fixture['user']->id;
        $venueData = $this->venueData();
        $assetData = $this->assetData();
        $binding = ['control_family' => 'kiosk', 'adapter_key' => 'fake', 'opaque_reference' => 'opaque:kiosk:1'];

        $venue = $actions['createVenue']->execute($tenantId, $actorId, $venueData, 'create-correlation');
        $actions['updateVenue']->execute($tenantId, $actorId, $venue->public_id, 1, $venueData + ['description_en' => 'Updated'], 'update-correlation');
        $actions['status']->execute($tenantId, $actorId, $venue->public_id, 'active', 'status-correlation');
        $asset = $actions['createAsset']->execute($tenantId, $actorId, $venue->public_id, $assetData, $binding, 'asset-create');
        $actions['updateAsset']->execute($tenantId, $actorId, $asset->public_id, 1, $assetData, $binding, 'asset-update');
        $actions['availability']->execute($tenantId, $actorId, $asset->public_id, 2, [[
            'local_from' => '2027-01-10 09:00:00', 'local_until' => '2027-01-10 18:00:00',
        ]], 'availability-correlation');
        $actions['publish']->execute($tenantId, $actorId, $asset->public_id, 'publish-correlation');
        $actions['withdraw']->execute($tenantId, $actorId, $asset->public_id, 'withdraw-correlation');
        $actions['retire']->execute($tenantId, $actorId, $asset->public_id, 'retire-correlation');
        $actions['archive']->execute($tenantId, $actorId, $venue->public_id, 'archive-correlation');

        self::assertSame([
            'venue.created', 'venue.updated', 'venue.status_changed', 'venue_asset.created',
            'venue_asset.updated', 'venue_asset.availability_replaced', 'venue_asset.published',
            'venue_asset.publication_withdrawn', 'venue_asset.retired', 'venue.archived',
        ], array_map(fn (MarketplaceAuditEvent $event) => $event->action, $writer->events));
        foreach ($writer->events as $event) {
            self::assertSame($tenantId, $event->ownerTenantId);
            self::assertSame($actorId, $event->actorUserId);
            $payload = json_encode($event->payload, JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString('opaque:kiosk:1', $payload);
            self::assertStringNotContainsString('owner-private@example.test', $payload);
        }
    }

    public function test_audit_failure_rolls_back_the_mutation(): void
    {
        [$fixture, $writer, $actions] = $this->fixture();
        $writer->fail = true;

        try {
            $actions['createVenue']->execute(
                (int) $fixture['tenant']->id,
                (int) $fixture['user']->id,
                $this->venueData(),
                'rollback-correlation',
            );
            self::fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('forced_audit_failure', $exception->getMessage());
        }

        self::assertSame(0, Venue::query()->count());
    }

    private function fixture(): array
    {
        $fixture = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        app(TenantContextStore::class)->bind($fixture['tenant'], $fixture['membership'], $fixture['user']);
        $writer = new class implements MarketplaceAuditWriter
        {
            public array $events = [];

            public bool $fail = false;

            public function write(MarketplaceAuditEvent $event): void
            {
                if ($this->fail) {
                    throw new RuntimeException('forced_audit_failure');
                }
                $this->events[] = $event;
            }
        };
        $tx = new AuditedTransaction;
        $lifecycle = new VenueLifecyclePolicy;
        $assetPolicy = new VenueAssetBindingPolicy(new MarketplaceCapabilityRegistry);
        $readiness = new PublicationReadinessPolicy(new MarketplaceCapabilityRegistry);
        $eligibility = new FakeOrganizationEligibility;

        return [$fixture, $writer, [
            'createVenue' => new CreateVenueAction($eligibility, $tx, $writer, $lifecycle),
            'updateVenue' => new UpdateVenueAction($tx, $writer, $lifecycle),
            'status' => new ChangeVenueStatusAction($tx, $writer, $lifecycle),
            'archive' => new ArchiveVenueAction($tx, $writer, $lifecycle),
            'createAsset' => new CreateVenueAssetAction($tx, $writer, $assetPolicy),
            'updateAsset' => new UpdateVenueAssetAction($tx, $writer, $assetPolicy),
            'retire' => new RetireVenueAssetAction($tx, $writer),
            'availability' => new ReplaceAssetAvailabilityAction($tx, $writer),
            'publish' => new PublishVenueAssetAction($tx, $writer, $readiness),
            'withdraw' => new WithdrawVenueAssetPublicationAction($tx, $writer),
        ]];
    }

    private function venueData(): array
    {
        return [
            'name_en' => 'Riyadh Expo', 'name_ar' => 'معرض الرياض',
            'address_en' => 'King Road', 'address_ar' => 'طريق الملك',
            'country_code' => 'SA', 'city_code' => 'riyadh', 'timezone' => 'Asia/Riyadh',
            'business_contact_email' => 'owner-private@example.test', 'publish_contact' => false,
        ];
    }

    private function assetData(): array
    {
        return [
            'asset_type' => 'kiosk', 'name_en' => 'Kiosk', 'name_ar' => 'كشك',
            'location_en' => 'Hall A', 'location_ar' => 'القاعة أ',
            'capabilities' => ['kiosk.manage'], 'capacity_per_minute' => 10,
            'operational_status' => 'active', 'pricing_model' => 'per_hour',
            'price_minor' => 1000, 'currency' => 'SAR',
        ];
    }
}
