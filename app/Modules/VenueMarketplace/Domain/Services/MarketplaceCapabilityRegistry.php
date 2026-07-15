<?php

namespace App\Modules\VenueMarketplace\Domain\Services;

use InvalidArgumentException;

final class MarketplaceCapabilityRegistry
{
    private const DEFINITIONS = [
        'turnstile' => ['control_family' => 'acs', 'module' => 'access_control', 'capabilities' => ['acs.configure']],
        'security_gate' => ['control_family' => 'acs', 'module' => 'access_control', 'capabilities' => ['acs.configure']],
        'camera' => ['control_family' => 'catalog_only', 'module' => 'catalog_only', 'capabilities' => []],
        'kiosk' => ['control_family' => 'kiosk', 'module' => 'kiosk', 'capabilities' => ['kiosk.manage']],
        'printer' => ['control_family' => 'printer', 'module' => 'badge_printing', 'capabilities' => ['badge.print']],
        'scanner' => ['control_family' => 'scanner', 'module' => 'scanning', 'capabilities' => ['checkin.scan.submit']],
        'access_lane' => ['control_family' => 'acs', 'module' => 'access_control', 'capabilities' => ['acs.configure']],
        'access_zone' => ['control_family' => 'acs', 'module' => 'access_control', 'capabilities' => ['acs.configure']],
    ];

    public function assetTypes(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public function definition(string $assetType): array
    {
        return self::DEFINITIONS[$assetType]
            ?? throw new InvalidArgumentException('Unknown marketplace asset type.');
    }

    public function assertCapabilities(string $assetType, array $capabilities): void
    {
        $allowed = $this->definition($assetType)['capabilities'];

        foreach (array_unique($capabilities) as $capability) {
            if (! is_string($capability) || ! in_array($capability, $allowed, true)) {
                throw new InvalidArgumentException('Capability is not allowed for this asset type.');
            }
        }
    }

    public function isCatalogOnly(string $assetType): bool
    {
        return $this->definition($assetType)['control_family'] === 'catalog_only';
    }
}
