<?php

namespace Tests\Unit\VenueMarketplace;

use App\Modules\VenueMarketplace\Domain\Services\ReservationConflictDetector;
use App\Modules\VenueMarketplace\Domain\ValueObjects\RentalWindow;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ReservationConflictDetectorTest extends TestCase
{
    private const ASSET_A = '01J2V5H5J7D3QH6J2SZ7M4H0AB';

    private const ASSET_B = '01J2V5H5J7D3QH6J2SZ7M4H0AC';

    private const RENTAL_A = '01J2V5H5J7D3QH6J2SZ7M4H0AD';

    private ReservationConflictDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new ReservationConflictDetector;
    }

    public function test_half_open_adjacent_windows_do_not_conflict(): void
    {
        $requested = $this->window('2026-07-14T10:00:00Z', '2026-07-14T11:00:00Z');

        self::assertSame([], $this->detector->findConflicts($requested, [
            $this->reservation('2026-07-14T09:00:00Z', '2026-07-14T10:00:00Z'),
            $this->reservation('2026-07-14T11:00:00Z', '2026-07-14T12:00:00Z'),
        ]));
    }

    public function test_every_overlap_direction_conflicts(): void
    {
        $requested = $this->window('2026-07-14T10:00:00Z', '2026-07-14T12:00:00Z');

        foreach ([
            ['2026-07-14T09:00:00Z', '2026-07-14T11:00:00Z'],
            ['2026-07-14T11:00:00Z', '2026-07-14T13:00:00Z'],
            ['2026-07-14T10:30:00Z', '2026-07-14T11:30:00Z'],
            ['2026-07-14T09:00:00Z', '2026-07-14T13:00:00Z'],
            ['2026-07-14T10:00:00Z', '2026-07-14T12:00:00Z'],
        ] as [$from, $until]) {
            $conflicts = $this->detector->findConflicts($requested, [
                $this->reservation($from, $until),
            ]);

            self::assertSame([[
                'asset_public_id' => self::ASSET_A,
                'rental_public_id' => self::RENTAL_A,
            ]], $conflicts);
        }
    }

    public function test_released_and_completed_reservations_do_not_block(): void
    {
        $requested = $this->window('2026-07-14T10:00:00Z', '2026-07-14T12:00:00Z');

        self::assertSame([], $this->detector->findConflicts($requested, [
            $this->reservation('2026-07-14T10:30:00Z', '2026-07-14T11:30:00Z', 'released'),
            $this->reservation('2026-07-14T10:30:00Z', '2026-07-14T11:30:00Z', 'completed'),
        ]));
    }

    public function test_conflicts_are_asset_specific_and_return_only_safe_opaque_metadata(): void
    {
        $requested = $this->window('2026-07-14T10:00:00Z', '2026-07-14T12:00:00Z');
        $reservations = [
            $this->reservation(
                '2026-07-14T10:30:00Z',
                '2026-07-14T11:30:00Z',
                assetId: 22,
                assetPublicId: self::ASSET_B,
            ),
            $this->reservation(
                '2026-07-14T10:30:00Z',
                '2026-07-14T11:30:00Z',
                assetId: 11,
                assetPublicId: self::ASSET_A,
            ),
        ];

        $conflicts = $this->detector->findConflicts($requested, $reservations, assetIds: [11]);

        self::assertSame([[
            'asset_public_id' => self::ASSET_A,
            'rental_public_id' => self::RENTAL_A,
        ]], $conflicts);
        self::assertSame(['asset_public_id', 'rental_public_id'], array_keys($conflicts[0]));
    }

    public function test_asset_lock_order_is_unique_ascending_and_deterministic(): void
    {
        self::assertSame([2, 5, 10, 31], $this->detector->orderedAssetIds([31, 5, 10, 2, 5]));
    }

    public function test_venue_local_and_utc_windows_normalize_to_the_same_instants(): void
    {
        $requested = $this->window(
            '2026-07-14T13:00:00+03:00',
            '2026-07-14T14:00:00+03:00',
        );

        self::assertSame([[
            'asset_public_id' => self::ASSET_A,
            'rental_public_id' => self::RENTAL_A,
        ]], $this->detector->findConflicts($requested, [
            $this->reservation('2026-07-14T10:30:00Z', '2026-07-14T10:45:00Z'),
        ]));
    }

    public function test_conflict_output_is_stably_sorted_and_deduplicated(): void
    {
        $requested = $this->window('2026-07-14T10:00:00Z', '2026-07-14T12:00:00Z');
        $reservationB = $this->reservation(
            '2026-07-14T10:30:00Z',
            '2026-07-14T11:30:00Z',
            assetId: 22,
            assetPublicId: self::ASSET_B,
        );
        $reservationA = $this->reservation('2026-07-14T10:30:00Z', '2026-07-14T11:30:00Z');

        self::assertSame([
            ['asset_public_id' => self::ASSET_A, 'rental_public_id' => self::RENTAL_A],
            ['asset_public_id' => self::ASSET_B, 'rental_public_id' => self::RENTAL_A],
        ], $this->detector->findConflicts($requested, [$reservationB, $reservationA, $reservationA]));
    }

    private function window(string $from, string $until): RentalWindow
    {
        return new RentalWindow(new DateTimeImmutable($from), new DateTimeImmutable($until));
    }

    private function reservation(
        string $from,
        string $until,
        string $status = 'reserved',
        int $assetId = 11,
        string $assetPublicId = self::ASSET_A,
    ): array {
        return [
            'venue_asset_id' => $assetId,
            'asset_public_id' => $assetPublicId,
            'rental_public_id' => self::RENTAL_A,
            'status' => $status,
            'reserved_from' => $from,
            'reserved_until' => $until,
            'tenant_id' => 999,
            'organizer_tenant_id' => 888,
            'participant_email' => 'must-not-leak@example.test',
        ];
    }
}
