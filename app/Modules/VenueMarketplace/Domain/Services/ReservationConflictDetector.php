<?php

namespace App\Modules\VenueMarketplace\Domain\Services;

use App\Modules\VenueMarketplace\Domain\ValueObjects\OpaqueMarketplaceId;
use App\Modules\VenueMarketplace\Domain\ValueObjects\RentalWindow;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

final class ReservationConflictDetector
{
    private const NON_BLOCKING_STATUSES = ['released', 'completed'];

    /**
     * @param  list<array<string, mixed>>  $reservations
     * @param  list<int>|null  $assetIds
     * @return list<array{asset_public_id: string, rental_public_id: string}>
     */
    public function findConflicts(
        RentalWindow $requestedWindow,
        array $reservations,
        ?array $assetIds = null,
    ): array {
        $requested = $this->utcWindow($requestedWindow->startsAt, $requestedWindow->endsAt);
        $selectedAssets = $assetIds === null
            ? null
            : array_fill_keys($this->orderedAssetIds($assetIds), true);
        $conflicts = [];

        foreach ($reservations as $reservation) {
            $assetId = $reservation['venue_asset_id'] ?? null;

            if (! is_int($assetId) || $assetId < 1) {
                throw new InvalidArgumentException('Reservation venue asset ID must be a positive integer.');
            }

            if ($selectedAssets !== null && ! isset($selectedAssets[$assetId])) {
                continue;
            }

            $status = $reservation['status'] ?? null;

            if (is_string($status) && in_array($status, self::NON_BLOCKING_STATUSES, true)) {
                continue;
            }

            $existing = $this->utcWindow(
                $this->date($reservation['reserved_from'] ?? null),
                $this->date($reservation['reserved_until'] ?? null),
            );

            if (! $this->overlaps($requested, $existing)) {
                continue;
            }

            $assetPublicId = (string) new OpaqueMarketplaceId($this->opaqueId(
                $reservation['asset_public_id'] ?? null,
            ));
            $rentalPublicId = (string) new OpaqueMarketplaceId($this->opaqueId(
                $reservation['rental_public_id'] ?? null,
            ));

            $conflicts[$assetPublicId."\0".$rentalPublicId] = [
                'asset_public_id' => $assetPublicId,
                'rental_public_id' => $rentalPublicId,
            ];
        }

        ksort($conflicts, SORT_STRING);

        return array_values($conflicts);
    }

    /**
     * Approval callers must lock venue assets in this order before scanning and
     * inserting reservations.
     *
     * @param  list<int>  $assetIds
     * @return list<int>
     */
    public function orderedAssetIds(array $assetIds): array
    {
        foreach ($assetIds as $assetId) {
            if (! is_int($assetId) || $assetId < 1) {
                throw new InvalidArgumentException('Venue asset IDs must be positive integers.');
            }
        }

        $ordered = array_values(array_unique($assetIds, SORT_NUMERIC));
        sort($ordered, SORT_NUMERIC);

        return $ordered;
    }

    private function overlaps(RentalWindow $left, RentalWindow $right): bool
    {
        return $left->startsAt < $right->endsAt && $right->startsAt < $left->endsAt;
    }

    private function utcWindow(DateTimeInterface $startsAt, DateTimeInterface $endsAt): RentalWindow
    {
        $utc = new DateTimeZone('UTC');

        return new RentalWindow(
            DateTimeImmutable::createFromInterface($startsAt)->setTimezone($utc),
            DateTimeImmutable::createFromInterface($endsAt)->setTimezone($utc),
        );
    }

    private function date(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Reservation timestamps must be valid date-times.');
        }

        return new DateTimeImmutable($value);
    }

    private function opaqueId(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Conflict metadata requires opaque public IDs.');
        }

        return $value;
    }
}
