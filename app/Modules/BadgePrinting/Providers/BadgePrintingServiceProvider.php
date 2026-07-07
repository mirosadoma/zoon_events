<?php

namespace App\Modules\BadgePrinting\Providers;

use App\Modules\Audit\Application\Listeners\Phase3\BadgePrintAuditListener;
use App\Modules\Audit\Application\Listeners\Phase3\BadgeTemplateAuditListener;
use App\Modules\BadgePrinting\Contracts\PrinterAdapter;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobCreated;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobFailed;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobPrinted;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobReprinted;
use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateActivated;
use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateCreated;
use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateDeactivated;
use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateDeleted;
use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateUpdated;
use App\Modules\BadgePrinting\Testing\FakePrinterAdapter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class BadgePrintingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FakePrinterAdapter::class);

        $this->app->bind(PrinterAdapter::class, function ($app): PrinterAdapter {
            return match (config('printing.default_printer_adapter', 'fake')) {
                default => $app->make(FakePrinterAdapter::class),
            };
        });
    }

    public function boot(): void
    {
        Event::listen(BadgePrintJobCreated::class, [BadgePrintAuditListener::class, 'handleCreated']);
        Event::listen(BadgePrintJobPrinted::class, [BadgePrintAuditListener::class, 'handlePrinted']);
        Event::listen(BadgePrintJobFailed::class, [BadgePrintAuditListener::class, 'handleFailed']);
        Event::listen(BadgePrintJobReprinted::class, [BadgePrintAuditListener::class, 'handleReprinted']);
        Event::listen(BadgeTemplateCreated::class, [BadgeTemplateAuditListener::class, 'handleCreated']);
        Event::listen(BadgeTemplateUpdated::class, [BadgeTemplateAuditListener::class, 'handleUpdated']);
        Event::listen(BadgeTemplateActivated::class, [BadgeTemplateAuditListener::class, 'handleActivated']);
        Event::listen(BadgeTemplateDeactivated::class, [BadgeTemplateAuditListener::class, 'handleDeactivated']);
        Event::listen(BadgeTemplateDeleted::class, [BadgeTemplateAuditListener::class, 'handleDeleted']);
    }
}
