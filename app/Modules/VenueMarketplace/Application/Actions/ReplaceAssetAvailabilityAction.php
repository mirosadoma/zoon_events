<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\AssetAvailabilityWindow;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAsset;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

final readonly class ReplaceAssetAvailabilityAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private MarketplaceAuditWriter $audit,
    ) {}

    /** @return list<AssetAvailabilityWindow> */
    public function execute(
        int $tenantId,
        int $actorUserId,
        string $assetPublicId,
        int $expectedVersion,
        array $windows,
        string $correlationId,
    ): array {
        return $this->transactions->run(
            function () use ($tenantId, $actorUserId, $assetPublicId, $expectedVersion, $windows): array {
                $asset = VenueAsset::query()
                    ->forTenant((string) $tenantId)
                    ->where('public_id', $assetPublicId)
                    ->lockForUpdate()
                    ->first();

                if ($asset === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_NOT_FOUND);
                }
                if ($asset->isRetired()) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_UNAVAILABLE);
                }
                if ((int) $asset->version !== $expectedVersion) {
                    throw new MarketplaceDomainException(
                        Phase6Problem::MARKETPLACE_AVAILABILITY_CONFLICT,
                        status: 409,
                    );
                }

                $normalized = $this->normalize($windows, $asset->venue()->value('timezone') ?? 'UTC');
                AssetAvailabilityWindow::query()
                    ->forTenant((string) $tenantId)
                    ->where('venue_asset_id', $asset->id)
                    ->delete();

                $created = [];
                foreach ($normalized as $window) {
                    $created[] = AssetAvailabilityWindow::query()->forceCreate([
                        ...$window,
                        'tenant_id' => $tenantId,
                        'venue_asset_id' => $asset->id,
                        'public_id' => (string) Str::ulid(),
                        'version' => 1,
                        'created_by_user_id' => $actorUserId,
                        'updated_by_user_id' => $actorUserId,
                    ]);
                }

                $asset->forceFill(['version' => $asset->version + 1, 'updated_by_user_id' => $actorUserId])->save();

                return $created;
            },
            fn (array $created) => $this->audit->write(new MarketplaceAuditEvent(
                'venue_asset.availability_replaced',
                'owner',
                'succeeded',
                $correlationId,
                $assetPublicId,
                ['window_count' => count($created), 'expected_version' => $expectedVersion],
                ownerTenantId: $tenantId,
                actorUserId: $actorUserId,
            )),
        );
    }

    private function normalize(array $windows, string $timezone): array
    {
        $normalized = [];
        foreach ($windows as $window) {
            try {
                if (isset($window['available_from'], $window['available_until'])) {
                    $localFrom = CarbonImmutable::parse((string) $window['available_from'])->setTimezone($timezone);
                    $localUntil = CarbonImmutable::parse((string) $window['available_until'])->setTimezone($timezone);
                } else {
                    $localFrom = CarbonImmutable::createFromFormat('Y-m-d H:i:s', (string) ($window['local_from'] ?? ''), $timezone);
                    $localUntil = CarbonImmutable::createFromFormat('Y-m-d H:i:s', (string) ($window['local_until'] ?? ''), $timezone);
                }
            } catch (Throwable) {
                $this->deny();
            }

            if ($localFrom === false || $localUntil === false || $localUntil <= $localFrom
                || ! in_array($window['status'] ?? 'available', ['available', 'blocked'], true)) {
                $this->deny();
            }

            $normalized[] = [
                'available_from' => $localFrom->utc(),
                'available_until' => $localUntil->utc(),
                'local_from' => $localFrom->format('Y-m-d H:i:s'),
                'local_until' => $localUntil->format('Y-m-d H:i:s'),
                'source_timezone' => $timezone,
                'status' => $window['status'] ?? 'available',
                'reason_code' => $window['reason_code'] ?? null,
            ];
        }

        usort($normalized, fn (array $a, array $b) => $a['available_from'] <=> $b['available_from']);
        for ($index = 1; $index < count($normalized); $index++) {
            if ($normalized[$index]['available_from'] < $normalized[$index - 1]['available_until']) {
                $this->deny();
            }
        }

        return $normalized;
    }

    private function deny(): never
    {
        throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_AVAILABILITY_CONFLICT);
    }
}
