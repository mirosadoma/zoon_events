<?php

namespace App\Modules\VenueMarketplace\Domain\Services;

use App\Modules\VenueMarketplace\Domain\ValueObjects\Money;
use App\Modules\VenueMarketplace\Domain\ValueObjects\RentalWindow;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceCatalogPublication;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use InvalidArgumentException;

final class MarketplaceQuoteService
{
    public const VERSION = 1;

    public function __construct(private readonly ?Repository $cache = null) {}

    /** @param iterable<MarketplaceCatalogPublication|array{publication:MarketplaceCatalogPublication,quantity?:int,selected_capabilities?:list<string>}> $selections */
    public function calculate(iterable $selections, RentalWindow $window): array
    {
        $normalized = [];
        foreach ($selections as $selection) {
            $publication = $selection instanceof MarketplaceCatalogPublication
                ? $selection
                : $selection['publication'];
            $quantity = $selection instanceof MarketplaceCatalogPublication ? 1 : (int) ($selection['quantity'] ?? 1);
            $selectedCapabilities = $selection instanceof MarketplaceCatalogPublication
                ? ($publication->relationLoaded('capabilities')
                    ? $publication->getRelation('capabilities')->pluck('capability_code')->values()->all()
                    : [])
                : array_values($selection['selected_capabilities'] ?? []);
            if ($quantity < 1 || (int) $publication->price_minor <= 0) {
                throw new InvalidArgumentException('Quote quantity and price must be positive integers.');
            }
            $normalized[] = compact('publication', 'quantity', 'selectedCapabilities');
        }
        if ($normalized === []) {
            throw new InvalidArgumentException('A quote requires at least one publication.');
        }

        usort(
            $normalized,
            fn (array $left, array $right): int => strcmp($left['publication']->public_id, $right['publication']->public_id),
        );
        $venuePublicId = $normalized[0]['publication']->venue_public_id;
        $currency = $normalized[0]['publication']->currency;
        $timezone = $normalized[0]['publication']->timezone;
        $total = new Money(0, $currency);
        $lines = [];

        foreach ($normalized as $selection) {
            /** @var MarketplaceCatalogPublication $publication */
            $publication = $selection['publication'];
            if ($publication->venue_public_id !== $venuePublicId) {
                throw new InvalidArgumentException('Quote selections must belong to one venue.');
            }
            if ($publication->currency !== $currency) {
                throw new InvalidArgumentException('Quote selections must use one currency.');
            }
            $units = $this->billableUnits($publication->pricing_model, $window, $timezone);
            $minor = $this->multiply((int) $publication->price_minor, $units, $selection['quantity']);
            $total = $total->add(new Money($minor, $currency));
            $lines[] = [
                'publication_public_id' => $publication->public_id,
                'publication_version' => (int) $publication->publication_version,
                'asset_public_id' => $publication->asset_public_id,
                'asset_name' => ['en' => $publication->asset_name_en, 'ar' => $publication->asset_name_ar],
                'asset_type' => $publication->asset_type,
                'selected_capabilities' => $selection['selectedCapabilities'],
                'pricing_model' => $publication->pricing_model,
                'unit_price_minor' => (int) $publication->price_minor,
                'quantity' => $selection['quantity'],
                'billable_units' => $units,
                'line_total_minor' => $minor,
                'currency' => $currency,
            ];
        }

        $facts = [
            'quote_version' => self::VERSION,
            'venue_public_id' => $venuePublicId,
            'venue_timezone' => $timezone,
            'requested_start_at' => CarbonImmutable::instance($window->startsAt)->utc()->toISOString(),
            'requested_end_at' => CarbonImmutable::instance($window->endsAt)->utc()->toISOString(),
            'lines' => $lines,
            'total_minor' => $total->minor,
            'currency' => $currency,
        ];

        $digest = hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->cache?->put("marketplace:quote:{$digest}", true, now()->addMinutes(15));

        return [
            'quote_digest' => $digest,
            ...$facts,
            'expires_at' => CarbonImmutable::now()->startOfSecond()->addMinutes(15)->toISOString(),
        ];
    }

    public function isCurrent(string $digest): bool
    {
        return $this->cache === null || $this->cache->has("marketplace:quote:{$digest}");
    }

    private function billableUnits(string $model, RentalWindow $window, string $timezone): int
    {
        $start = CarbonImmutable::instance($window->startsAt);
        $end = CarbonImmutable::instance($window->endsAt);

        return match ($model) {
            'per_hour' => max(1, intdiv($end->getTimestamp() - $start->getTimestamp() + 3599, 3600)),
            'per_day' => (int) $start->setTimezone($timezone)->startOfDay()
                ->diff($end->subMicrosecond()->setTimezone($timezone)->startOfDay())
                ->format('%a') + 1,
            'per_rental' => 1,
            default => throw new InvalidArgumentException('Unsupported marketplace pricing model.'),
        };
    }

    private function multiply(int $unitPriceMinor, int $units, int $quantity): int
    {
        if ($unitPriceMinor > intdiv(PHP_INT_MAX, $units)
            || $unitPriceMinor * $units > intdiv(PHP_INT_MAX, $quantity)) {
            throw new InvalidArgumentException('Quote amount overflow.');
        }

        return $unitPriceMinor * $units * $quantity;
    }
}
