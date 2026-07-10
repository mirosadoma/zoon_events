<?php

namespace App\Modules\Kiosk\Providers;

use App\Modules\Audit\Application\Listeners\Phase3\KioskAuditListener;
use App\Modules\Kiosk\Application\KioskFleetSummaryService;
use App\Modules\Kiosk\Contracts\KioskFleetSummaryProvider;
use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use App\Modules\Kiosk\Domain\Events\KioskPaired;
use App\Modules\Kiosk\Domain\Events\KioskRetired;
use App\Modules\Kiosk\Domain\Events\KioskStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class KioskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KioskSessionContextStore::class);
        $this->app->bind(KioskFleetSummaryProvider::class, KioskFleetSummaryService::class);
    }

    public function boot(): void
    {
        Event::listen(KioskPaired::class, [KioskAuditListener::class, 'handlePaired']);
        Event::listen(KioskRetired::class, [KioskAuditListener::class, 'handleRetired']);
        Event::listen(KioskStatusChanged::class, [KioskAuditListener::class, 'handleStatusChanged']);
    }
}
