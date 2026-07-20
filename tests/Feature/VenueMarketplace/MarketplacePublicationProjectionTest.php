<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\PublishVenueAssetAction;
use App\Modules\VenueMarketplace\Application\Actions\WithdrawVenueAssetPublicationAction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\MarketplaceCapabilityRegistry;
use App\Modules\VenueMarketplace\Domain\Services\PublicationReadinessPolicy;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\AssetAvailabilityWindow;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceCatalogPublication;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\Venue;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAsset;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAssetBinding;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

class MarketplacePublicationProjectionTest extends TestCase
{
    use BuildsTenantFixtures;
    use DatabaseTransactions;

    public function test_publication_is_an_immutable_allowlisted_projection_with_stable_source_ids(): void
    {
        [$publish, , $venue, $asset] = $this->fixture();
        DB::connection()->enableQueryLog();
        $publication = $publish->execute((int) $venue->tenant_id, 1, $asset->public_id, 'publish-1');
        $serialized = json_encode($publication->load('capabilities')->toArray(), JSON_THROW_ON_ERROR);
        $queries = json_encode(DB::getQueryLog(), JSON_THROW_ON_ERROR);

        self::assertSame($venue->public_id, $publication->venue_public_id);
        self::assertSame($asset->public_id, $publication->asset_public_id);
        self::assertSame(['kiosk.manage'], $publication->capabilities->pluck('capability_code')->all());
        self::assertStringNotContainsString('private-owner@example.test', $serialized);
        self::assertStringNotContainsString('opaque:kiosk:secret-looking-value', $serialized);
        self::assertStringNotContainsString('adapter_key', $serialized);
        self::assertStringNotContainsString('private-owner@example.test', $queries);
        self::assertStringNotContainsString('opaque:kiosk:secret-looking-value', $queries);

        $asset->forceFill(['name_en' => 'Changed privately', 'version' => 2])->save();
        self::assertSame('Kiosk One', $publication->fresh()->asset_name_en);
    }

    public function test_withdrawal_removes_active_projection_without_deleting_history(): void
    {
        [$publish, $withdraw, $venue, $asset] = $this->fixture();
        $publication = $publish->execute((int) $venue->tenant_id, 1, $asset->public_id, 'publish');
        $withdraw->execute((int) $venue->tenant_id, 1, $asset->public_id, 'withdraw');

        self::assertSame('withdrawn', $publication->fresh()->status);
        self::assertNotNull($publication->fresh()->withdrawn_at);
        self::assertSame(0, MarketplaceCatalogPublication::query()->where('status', 'active')->count());
    }

    public function test_suspended_venues_retired_assets_missing_availability_and_obligations_fail_closed(): void
    {
        [$publish, , $venue, $asset, $readiness] = $this->fixture();

        foreach ([
            function () use ($publish, $venue, $asset): void {
                $venue->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();
                $publish->execute((int) $venue->tenant_id, 1, $asset->public_id, 'suspended');
            },
            function () use ($publish, $venue, $asset): void {
                $venue->forceFill(['status' => 'active', 'suspended_at' => null])->save();
                $asset->forceFill(['operational_status' => 'retired', 'retired_at' => now()])->save();
                $publish->execute((int) $venue->tenant_id, 1, $asset->public_id, 'retired');
            },
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected publication readiness denial.');
            } catch (MarketplaceDomainException $exception) {
                self::assertContains($exception->reasonCode, [
                    Phase6Problem::MARKETPLACE_VENUE_NOT_PUBLISHABLE,
                    Phase6Problem::MARKETPLACE_ASSET_NOT_PUBLISHABLE,
                ]);
            }
        }

        $venue->forceFill(['status' => 'active', 'suspended_at' => null])->save();
        $asset->forceFill(['operational_status' => 'active', 'retired_at' => null])->save();
        AssetAvailabilityWindow::query()->delete();
        $this->assertDenied(fn () => $publish->execute((int) $venue->tenant_id, 1, $asset->public_id, 'unavailable'));
        $this->assertDenied(fn () => $readiness->assertReady($venue, $asset, $asset->binding, 1, true));
    }

    private function assertDenied(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected asset publication denial.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_ASSET_NOT_PUBLISHABLE, $exception->reasonCode);
        }
    }

    private function fixture(): array
    {
        $fixture = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        app(TenantContextStore::class)->bind($fixture['tenant'], $fixture['membership'], $fixture['user']);
        $venue = Venue::query()->forceCreate([
            'tenant_id' => $fixture['tenant']->id, 'public_id' => (string) Str::ulid(),
            'name_en' => 'Riyadh Expo', 'name_ar' => 'معرض الرياض',
            'description_en' => 'Public venue', 'description_ar' => 'موقع عام',
            'address_en' => 'King Road', 'address_ar' => 'طريق الملك',
            'country_code' => 'SA', 'city_code' => 'riyadh', 'timezone' => 'Asia/Riyadh',
            'business_contact_email' => 'private-owner@example.test', 'publish_contact' => false,
            'status' => 'active', 'version' => 1, 'activated_at' => now(),
            'created_by_user_id' => $fixture['user']->id, 'updated_by_user_id' => $fixture['user']->id,
        ]);
        $asset = VenueAsset::query()->forceCreate([
            'tenant_id' => $fixture['tenant']->id, 'venue_id' => $venue->id,
            'public_id' => (string) Str::ulid(), 'asset_type' => 'kiosk',
            'name_en' => 'Kiosk One', 'name_ar' => 'كشك واحد',
            'description_en' => 'Public asset', 'description_ar' => 'أصل عام',
            'location_en' => 'Hall A', 'location_ar' => 'القاعة أ',
            'capabilities' => ['kiosk.manage'], 'operational_status' => 'active',
            'pricing_model' => 'per_hour', 'price_minor' => 1000, 'currency' => 'SAR',
            'version' => 1, 'created_by_user_id' => $fixture['user']->id,
            'updated_by_user_id' => $fixture['user']->id,
        ]);
        VenueAssetBinding::query()->forceCreate([
            'tenant_id' => $fixture['tenant']->id, 'venue_asset_id' => $asset->id,
            'control_family' => 'kiosk', 'adapter_key' => 'fake',
            'opaque_reference' => 'opaque:kiosk:secret-looking-value', 'status' => 'active',
        ]);
        AssetAvailabilityWindow::query()->forceCreate([
            'tenant_id' => $fixture['tenant']->id, 'venue_asset_id' => $asset->id,
            'public_id' => (string) Str::ulid(), 'available_from' => '2027-01-10 06:00:00',
            'available_until' => '2027-01-10 18:00:00', 'local_from' => '2027-01-10 09:00:00',
            'local_until' => '2027-01-10 21:00:00', 'source_timezone' => 'Asia/Riyadh',
            'status' => 'available', 'version' => 1, 'created_by_user_id' => $fixture['user']->id,
            'updated_by_user_id' => $fixture['user']->id,
        ]);
        $audit = new class implements MarketplaceAuditWriter
        {
            public function write(MarketplaceAuditEvent $event): void {}
        };
        $readiness = new PublicationReadinessPolicy(new MarketplaceCapabilityRegistry);

        return [
            new PublishVenueAssetAction(new AuditedTransaction, $audit, $readiness),
            new WithdrawVenueAssetPublicationAction(new AuditedTransaction, $audit),
            $venue, $asset, $readiness,
        ];
    }
}
