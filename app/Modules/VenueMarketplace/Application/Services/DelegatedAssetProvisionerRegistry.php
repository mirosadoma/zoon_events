<?php

namespace App\Modules\VenueMarketplace\Application\Services;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterAssetPort;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskAssetPort;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerAssetPort;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use Illuminate\Contracts\Container\Container;

final readonly class DelegatedAssetProvisionerRegistry
{
    public function __construct(private Container $container) {}

    public function resolve(string $resourceModule): DelegatedAcsAssetPort|DelegatedKioskAssetPort|DelegatedPrinterAssetPort|DelegatedScannerAssetPort|CatalogOnlyCameraProvisioner
    {
        return match ($resourceModule) {
            'access_control' => $this->container->make(DelegatedAcsAssetPort::class),
            'kiosk' => $this->container->make(DelegatedKioskAssetPort::class),
            'badge_printing' => $this->container->make(DelegatedPrinterAssetPort::class),
            'scanning' => $this->container->make(DelegatedScannerAssetPort::class),
            'catalog_only' => $this->container->make(CatalogOnlyCameraProvisioner::class),
            default => throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ADAPTER_UNAVAILABLE),
        };
    }
}
