<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ReplaceAssetAvailabilityAction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\AssetAvailabilityWindow;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\Venue;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAsset;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

class AssetAvailabilityWindowTest extends TestCase
{
    use BuildsTenantFixtures;
    use DatabaseTransactions;

    public function test_local_windows_are_normalized_to_utc_and_adjacent_blackouts_are_atomic(): void
    {
        [$action, $asset, $fixture] = $this->fixture();

        $windows = $action->execute(
            (int) $fixture['tenant']->id,
            (int) $fixture['user']->id,
            $asset->public_id,
            1,
            [
                ['local_from' => '2027-01-10 09:00:00', 'local_until' => '2027-01-10 12:00:00', 'status' => 'available'],
                ['local_from' => '2027-01-10 12:00:00', 'local_until' => '2027-01-10 13:00:00', 'status' => 'blocked', 'reason_code' => 'maintenance'],
            ],
            'availability-correlation',
        );

        self::assertCount(2, $windows);
        self::assertSame('2027-01-10 06:00:00', $windows[0]->available_from->utc()->format('Y-m-d H:i:s'));
        self::assertSame('blocked', $windows[1]->status);
        self::assertSame(2, $asset->fresh()->version);
    }

    public function test_overlap_or_stale_version_preserves_the_previous_schedule(): void
    {
        [$action, $asset, $fixture] = $this->fixture();
        $valid = [['local_from' => '2027-01-10 09:00:00', 'local_until' => '2027-01-10 12:00:00']];
        $action->execute((int) $fixture['tenant']->id, (int) $fixture['user']->id, $asset->public_id, 1, $valid, 'first');

        foreach ([
            [2, [
                ['local_from' => '2027-01-10 10:00:00', 'local_until' => '2027-01-10 13:00:00'],
                ['local_from' => '2027-01-10 12:00:00', 'local_until' => '2027-01-10 14:00:00'],
            ]],
            [1, [['local_from' => '2027-01-11 09:00:00', 'local_until' => '2027-01-11 12:00:00']]],
        ] as [$version, $invalid]) {
            try {
                $action->execute((int) $fixture['tenant']->id, (int) $fixture['user']->id, $asset->public_id, $version, $invalid, 'denied');
                self::fail('Expected schedule replacement to fail.');
            } catch (MarketplaceDomainException $exception) {
                self::assertSame(Phase6Problem::MARKETPLACE_AVAILABILITY_CONFLICT, $exception->reasonCode);
            }
            self::assertSame(
                '2027-01-10 09:00:00',
                AssetAvailabilityWindow::query()->sole()->local_from->format('Y-m-d H:i:s'),
            );
        }
    }

    public function test_cross_tenant_public_ids_and_retired_assets_fail_closed(): void
    {
        [$action, $asset, $fixture] = $this->fixture();

        foreach ([
            [(int) $fixture['tenant']->id + 999, $asset->public_id],
            [(int) $fixture['tenant']->id, (string) Str::ulid()],
        ] as [$tenantId, $publicId]) {
            try {
                $action->execute($tenantId, (int) $fixture['user']->id, $publicId, 1, [], 'denied');
                self::fail('Expected scoped asset lookup to fail.');
            } catch (MarketplaceDomainException $exception) {
                self::assertSame(Phase6Problem::MARKETPLACE_ASSET_NOT_FOUND, $exception->reasonCode);
            }
        }

        $asset->forceFill(['operational_status' => 'retired', 'retired_at' => now()])->save();
        try {
            $action->execute((int) $fixture['tenant']->id, (int) $fixture['user']->id, $asset->public_id, 2, [], 'retired');
            self::fail('Expected retired asset denial.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_ASSET_UNAVAILABLE, $exception->reasonCode);
        }
    }

    private function fixture(): array
    {
        $fixture = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        app(TenantContextStore::class)->bind($fixture['tenant'], $fixture['membership'], $fixture['user']);

        $venue = Venue::query()->forceCreate([
            'tenant_id' => $fixture['tenant']->id, 'public_id' => (string) Str::ulid(),
            'name_en' => 'Venue', 'name_ar' => 'موقع', 'address_en' => 'Address',
            'address_ar' => 'عنوان', 'country_code' => 'SA', 'city_code' => 'riyadh',
            'timezone' => 'Asia/Riyadh', 'status' => 'active', 'version' => 1,
            'activated_at' => now(), 'created_by_user_id' => $fixture['user']->id,
            'updated_by_user_id' => $fixture['user']->id,
        ]);
        $asset = VenueAsset::query()->forceCreate([
            'tenant_id' => $fixture['tenant']->id, 'venue_id' => $venue->id,
            'public_id' => (string) Str::ulid(), 'asset_type' => 'camera',
            'name_en' => 'Camera', 'name_ar' => 'كاميرا', 'location_en' => 'Hall',
            'location_ar' => 'قاعة', 'capabilities' => [], 'operational_status' => 'active',
            'pricing_model' => 'per_hour', 'price_minor' => 1000, 'currency' => 'SAR',
            'version' => 1, 'created_by_user_id' => $fixture['user']->id,
            'updated_by_user_id' => $fixture['user']->id,
        ]);

        $writer = new class implements MarketplaceAuditWriter
        {
            public array $events = [];

            public function write(MarketplaceAuditEvent $event): void
            {
                $this->events[] = $event;
            }
        };

        return [new ReplaceAssetAvailabilityAction(new AuditedTransaction, $writer), $asset, $fixture];
    }
}
