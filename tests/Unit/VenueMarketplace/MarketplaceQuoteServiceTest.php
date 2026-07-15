<?php

namespace Tests\Unit\VenueMarketplace;

use App\Modules\VenueMarketplace\Domain\Services\MarketplaceQuoteService;
use App\Modules\VenueMarketplace\Domain\ValueObjects\RentalWindow;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceCatalogPublication;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MarketplaceQuoteServiceTest extends TestCase
{
    public function test_started_units_and_totals_use_integer_minor_units(): void
    {
        CarbonImmutable::setTestNow('2026-07-14T12:00:00Z');
        $window = new RentalWindow(
            new DateTimeImmutable('2027-03-27T22:30:00Z'),
            new DateTimeImmutable('2027-03-28T00:01:00Z'),
        );
        $publications = [
            $this->publication('01ARZ3NDEKTSV4RRFFQ69G5FAV', 'per_hour', 1_001),
            $this->publication('01ARZ3NDEKTSV4RRFFQ69G5FAW', 'per_day', 2_003),
            $this->publication('01ARZ3NDEKTSV4RRFFQ69G5FAX', 'per_rental', 3_007),
        ];

        $quote = (new MarketplaceQuoteService)->calculate($publications, $window);

        self::assertSame([2, 2, 1], array_column($quote['lines'], 'billable_units'));
        self::assertSame([2_002, 4_006, 3_007], array_column($quote['lines'], 'line_total_minor'));
        self::assertSame(9_015, $quote['total_minor']);
        self::assertSame('SAR', $quote['currency']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $quote['quote_digest']);
        self::assertSame($quote, (new MarketplaceQuoteService)->calculate(array_reverse($publications), $window));
        CarbonImmutable::setTestNow();
    }

    public function test_mixed_currency_and_invalid_money_are_rejected(): void
    {
        $window = new RentalWindow(
            new DateTimeImmutable('2027-01-10T06:00:00Z'),
            new DateTimeImmutable('2027-01-10T07:00:00Z'),
        );
        $sar = $this->publication('01ARZ3NDEKTSV4RRFFQ69G5FAV', 'per_hour', 100);
        $usd = $this->publication('01ARZ3NDEKTSV4RRFFQ69G5FAW', 'per_hour', 100)
            ->forceFill(['currency' => 'USD']);

        try {
            (new MarketplaceQuoteService)->calculate([$sar, $usd], $window);
            self::fail('Expected mixed currency rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('currency', $exception->getMessage());
        }

        foreach ([0, -1] as $invalidMinor) {
            try {
                (new MarketplaceQuoteService)->calculate([
                    $this->publication('01ARZ3NDEKTSV4RRFFQ69G5FAX', 'per_hour', $invalidMinor),
                ], $window);
                self::fail('Expected non-positive money rejection.');
            } catch (\InvalidArgumentException $exception) {
                self::assertStringContainsString('positive', $exception->getMessage());
            }
        }
    }

    public function test_quantity_multiplies_integer_line_total(): void
    {
        $publication = $this->publication('01ARZ3NDEKTSV4RRFFQ69G5FAV', 'per_rental', 1_250);
        $quote = (new MarketplaceQuoteService)->calculate([[
            'publication' => $publication,
            'quantity' => 3,
            'selected_capabilities' => [],
        ]], new RentalWindow(
            new DateTimeImmutable('2027-01-10T06:00:00Z'),
            new DateTimeImmutable('2027-01-10T07:00:00Z'),
        ));

        self::assertSame(3, $quote['lines'][0]['quantity']);
        self::assertSame(3_750, $quote['lines'][0]['line_total_minor']);
        self::assertSame(3_750, $quote['total_minor']);
    }

    private function publication(string $publicId, string $model, int $minor): MarketplaceCatalogPublication
    {
        return (new MarketplaceCatalogPublication)->forceFill([
            'public_id' => $publicId,
            'venue_public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAA',
            'asset_public_id' => $publicId,
            'publication_version' => 1,
            'asset_name_en' => 'Asset',
            'asset_name_ar' => 'أصل',
            'timezone' => 'Europe/Berlin',
            'pricing_model' => $model,
            'price_minor' => $minor,
            'currency' => 'SAR',
        ]);
    }
}
