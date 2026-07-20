<?php

namespace Tests\Unit\VenueMarketplace;

use App\Modules\VenueMarketplace\Domain\ValueObjects\Money;
use App\Modules\VenueMarketplace\Domain\ValueObjects\OpaqueMarketplaceId;
use App\Modules\VenueMarketplace\Domain\ValueObjects\RentalWindow;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MarketplaceValueObjectsTest extends TestCase
{
    public function test_valid_marketplace_scalars_are_immutable(): void
    {
        $money = new Money(100, 'SAR');
        $window = new RentalWindow(
            new DateTimeImmutable('2026-07-14T10:00:00Z'),
            new DateTimeImmutable('2026-07-14T11:00:00Z'),
        );
        $id = new OpaqueMarketplaceId('01J2V5H5J7D3QH6J2SZ7M4H0AB');

        self::assertSame(100, $money->minor);
        self::assertTrue($window->contains(new DateTimeImmutable('2026-07-14T10:00:00Z')));
        self::assertSame('01J2V5H5J7D3QH6J2SZ7M4H0AB', (string) $id);
    }

    #[DataProvider('invalidScalars')]
    public function test_invalid_marketplace_scalars_are_rejected(callable $factory): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $factory();
    }

    public static function invalidScalars(): array
    {
        return [
            'negative money' => [static fn () => new Money(-1, 'SAR')],
            'lowercase currency' => [static fn () => new Money(1, 'sar')],
            'invalid window' => [static fn () => new RentalWindow(
                new DateTimeImmutable('2026-07-14T11:00:00Z'),
                new DateTimeImmutable('2026-07-14T10:00:00Z'),
            )],
            'non ulid' => [static fn () => new OpaqueMarketplaceId('42')],
        ];
    }
}
