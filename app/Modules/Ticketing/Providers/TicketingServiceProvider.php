<?php

namespace App\Modules\Ticketing\Providers;

use App\Modules\Audit\Application\Listeners\Phase1\RecordInventoryAudit;
use App\Modules\Ticketing\Application\Inventory\FreeTicketInventoryAllocator;
use App\Modules\Ticketing\Application\Inventory\InventoryService;
use App\Modules\Ticketing\Application\Inventory\PaidTicketInventoryAllocator;
use App\Modules\Ticketing\Application\Queries\DatabaseActiveTicketCounter;
use App\Modules\Ticketing\Application\Queries\DatabasePublicTicketCatalog;
use App\Modules\Ticketing\Contracts\ActiveTicketCounter;
use App\Modules\Ticketing\Contracts\FreeTicketAllocator;
use App\Modules\Ticketing\Contracts\PaidTicketAllocator;
use App\Modules\Ticketing\Contracts\PublicTicketCatalog;
use App\Modules\Ticketing\Contracts\TicketHoldReleaser;
use App\Modules\Ticketing\Contracts\TicketPriceReader;
use App\Modules\Ticketing\Domain\Events\InventoryStateChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class TicketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FreeTicketAllocator::class, FreeTicketInventoryAllocator::class);
        $this->app->bind(PublicTicketCatalog::class, DatabasePublicTicketCatalog::class);
        $this->app->singleton(PaidTicketInventoryAllocator::class);
        $this->app->bind(PaidTicketAllocator::class, PaidTicketInventoryAllocator::class);
        $this->app->bind(TicketPriceReader::class, PaidTicketInventoryAllocator::class);
        $this->app->bind(TicketHoldReleaser::class, InventoryService::class);
        $this->app->bind(ActiveTicketCounter::class, DatabaseActiveTicketCounter::class);
    }

    public function boot(): void
    {
        Event::listen(InventoryStateChanged::class, RecordInventoryAudit::class);
    }
}
