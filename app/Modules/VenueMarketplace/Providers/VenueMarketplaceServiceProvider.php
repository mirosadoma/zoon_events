<?php

namespace App\Modules\VenueMarketplace\Providers;

use App\Modules\Authorization\Application\Contracts\DelegatedControlGuard;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Application\Authorization\DatabaseDelegatedControlGuard;
use App\Modules\VenueMarketplace\Application\Listeners\SendDelegationNotifications;
use App\Modules\VenueMarketplace\Application\Listeners\SendRentalDecisionNotifications;
use App\Modules\VenueMarketplace\Application\Listeners\SendRentalRequestedNotifications;
use App\Modules\VenueMarketplace\Application\Listeners\SendSettlementDisputeNotifications;
use App\Modules\VenueMarketplace\Application\Listeners\WriteDelegationAudit;
use App\Modules\VenueMarketplace\Application\Listeners\WriteRentalRequestedAudit;
use App\Modules\VenueMarketplace\Application\Listeners\WriteSettlementDisputeAudit;
use App\Modules\VenueMarketplace\Application\Listeners\WriteVenueCatalogAudit;
use App\Modules\VenueMarketplace\Console\Commands\ActivateMarketplaceRentals;
use App\Modules\VenueMarketplace\Console\Commands\ExpireMarketplaceRentals;
use App\Modules\VenueMarketplace\Console\Commands\FinalizeMarketplaceStatements;
use App\Modules\VenueMarketplace\Domain\Events\DelegationProvisioned;
use App\Modules\VenueMarketplace\Domain\Events\DelegationReleased;
use App\Modules\VenueMarketplace\Domain\Events\DisputeOpened;
use App\Modules\VenueMarketplace\Domain\Events\DisputeResolved;
use App\Modules\VenueMarketplace\Domain\Events\RentalDecided;
use App\Modules\VenueMarketplace\Domain\Events\RentalRequested;
use App\Modules\VenueMarketplace\Domain\Events\StatementGenerated;
use App\Modules\VenueMarketplace\Domain\Events\StatementRevised;
use App\Modules\VenueMarketplace\Domain\Events\VenueCatalogEvents;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class VenueMarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WriteVenueCatalogAudit::class);
        $this->app->alias(WriteVenueCatalogAudit::class, MarketplaceAuditWriter::class);
        $this->app->bind(DelegatedControlGuard::class, DatabaseDelegatedControlGuard::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ActivateMarketplaceRentals::class,
                ExpireMarketplaceRentals::class,
                FinalizeMarketplaceStatements::class,
            ]);
        }

        Event::listen(VenueCatalogEvents::class, WriteVenueCatalogAudit::class);
        Event::listen(RentalRequested::class, WriteRentalRequestedAudit::class);
        Event::listen(RentalRequested::class, SendRentalRequestedNotifications::class);
        Event::listen(RentalDecided::class, SendRentalDecisionNotifications::class);
        Event::listen(DelegationProvisioned::class, [WriteDelegationAudit::class, 'handleProvisioned']);
        Event::listen(DelegationProvisioned::class, [SendDelegationNotifications::class, 'handleProvisioned']);
        Event::listen(DelegationReleased::class, [WriteDelegationAudit::class, 'handleReleased']);
        Event::listen(DelegationReleased::class, [SendDelegationNotifications::class, 'handleReleased']);

        Event::listen(StatementGenerated::class, WriteSettlementDisputeAudit::class);
        Event::listen(StatementRevised::class, WriteSettlementDisputeAudit::class);
        Event::listen(StatementRevised::class, SendSettlementDisputeNotifications::class);
        Event::listen(DisputeOpened::class, WriteSettlementDisputeAudit::class);
        Event::listen(DisputeResolved::class, WriteSettlementDisputeAudit::class);
        Event::listen(DisputeResolved::class, SendSettlementDisputeNotifications::class);
    }
}
