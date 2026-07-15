<?php

namespace Tests\Unit\VenueMarketplace;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\MarketplaceCapabilityRegistry;
use App\Modules\VenueMarketplace\Domain\Services\VenueAssetBindingPolicy;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use PHPUnit\Framework\TestCase;

class VenueAssetCapabilityTest extends TestCase
{
    public function test_all_eight_asset_types_accept_only_their_registered_capabilities_and_control_family(): void
    {
        $registry = new MarketplaceCapabilityRegistry;
        $policy = new VenueAssetBindingPolicy($registry);

        self::assertCount(8, $registry->assetTypes());
        foreach ($registry->assetTypes() as $assetType) {
            $definition = $registry->definition($assetType);
            $policy->validate($this->asset($assetType, $definition['capabilities']), [
                'control_family' => $definition['control_family'],
                'opaque_reference' => $assetType === 'camera' ? null : 'resource:'.str_replace('_', '-', $assetType),
            ]);
            $this->expectDenied(fn () => $policy->validate(
                $this->asset($assetType, ['unexpected.capability']),
                ['control_family' => $definition['control_family']],
            ));
        }
    }

    public function test_camera_is_catalog_only_and_controllable_assets_require_opaque_references(): void
    {
        $policy = new VenueAssetBindingPolicy(new MarketplaceCapabilityRegistry);

        $this->expectDenied(fn () => $policy->validate(
            $this->asset('camera', []),
            ['control_family' => 'acs', 'opaque_reference' => 'camera-feed-1'],
        ));
        $policy->validate($this->asset('camera', []), ['control_family' => 'catalog_only']);

        $this->expectDenied(fn () => $policy->validate(
            $this->asset('kiosk', ['kiosk.manage']),
            ['control_family' => 'kiosk'],
        ));
    }

    public function test_pricing_capacity_currency_and_secret_shaped_input_fail_closed(): void
    {
        $policy = new VenueAssetBindingPolicy(new MarketplaceCapabilityRegistry);
        $binding = ['control_family' => 'scanner', 'opaque_reference' => 'scanner:42'];

        foreach ([
            ['pricing_model' => 'dynamic'],
            ['price_minor' => -1],
            ['currency' => 'sar'],
            ['capacity_per_minute' => 0],
        ] as $invalid) {
            $this->expectDenied(fn () => $policy->validate(
                array_replace($this->asset('scanner', ['checkin.scan.submit']), $invalid),
                $binding,
            ));
        }

        foreach (['secret', 'password', 'credential', 'token', 'external_reference'] as $key) {
            $this->expectDenied(fn () => $policy->validate(
                $this->asset('scanner', ['checkin.scan.submit']),
                $binding + [$key => 'must-not-enter-marketplace'],
            ));
        }
    }

    private function expectDenied(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected asset validation to be denied.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_ASSET_NOT_PUBLISHABLE, $exception->reasonCode);
        }
    }

    private function asset(string $type, array $capabilities): array
    {
        return [
            'asset_type' => $type,
            'capabilities' => $capabilities,
            'capacity_per_minute' => 10,
            'pricing_model' => 'per_hour',
            'price_minor' => 2500,
            'currency' => 'SAR',
        ];
    }
}
