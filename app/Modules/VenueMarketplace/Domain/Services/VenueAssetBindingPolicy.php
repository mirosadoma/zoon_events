<?php

namespace App\Modules\VenueMarketplace\Domain\Services;

use App\Modules\VenueMarketplace\Domain\Enums\MarketplaceEnums;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use Throwable;

final readonly class VenueAssetBindingPolicy
{
    private const FORBIDDEN_KEYS = [
        'secret', 'password', 'credential', 'token', 'external_reference',
    ];

    public function __construct(private MarketplaceCapabilityRegistry $capabilities) {}

    public function validate(array $asset, array $binding): void
    {
        try {
            $definition = $this->capabilities->definition((string) ($asset['asset_type'] ?? ''));
            $this->capabilities->assertCapabilities(
                (string) $asset['asset_type'],
                is_array($asset['capabilities'] ?? null) ? $asset['capabilities'] : [],
            );
        } catch (Throwable) {
            $this->deny();
        }

        if (($binding['control_family'] ?? null) !== $definition['control_family']) {
            $this->deny();
        }

        if ($definition['control_family'] !== 'catalog_only'
            && (! is_string($binding['opaque_reference'] ?? null) || trim($binding['opaque_reference']) === '')) {
            $this->deny();
        }

        foreach (array_keys($binding) as $key) {
            $normalized = strtolower((string) $key);
            if (collect(self::FORBIDDEN_KEYS)->contains(fn (string $fragment) => str_contains($normalized, $fragment))) {
                $this->deny();
            }
        }

        if (! in_array($asset['pricing_model'] ?? null, MarketplaceEnums::PRICING_MODELS, true)
            || ! is_int($asset['price_minor'] ?? null) || $asset['price_minor'] < 0
            || ! is_string($asset['currency'] ?? null) || ! preg_match('/^[A-Z]{3}$/', $asset['currency'])
            || (isset($asset['capacity_per_minute'])
                && (! is_int($asset['capacity_per_minute']) || $asset['capacity_per_minute'] < 1))) {
            $this->deny();
        }
    }

    private function deny(): never
    {
        throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_NOT_PUBLISHABLE);
    }
}
