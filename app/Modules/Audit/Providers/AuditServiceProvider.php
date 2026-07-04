<?php

namespace App\Modules\Audit\Providers;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Audit\Application\Integrity\AuditIntegrityService;
use App\Modules\Audit\Application\Integrity\CanonicalAuditPayload;
use App\Modules\Audit\Application\Listeners\Phase1\RecordNotificationAudit;
use App\Modules\Audit\Contracts\AuditWriter as AuditWriterContract;
use App\Modules\Notifications\Domain\Events\NotificationTerminalStateReached;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditWriter::class);
        $this->app->alias(AuditWriter::class, AuditWriterContract::class);
        $this->app->singleton(CanonicalAuditPayload::class);
        $this->app->singleton(AuditIntegrityService::class);
    }

    public function boot(): void
    {
        Event::listen(NotificationTerminalStateReached::class, RecordNotificationAudit::class);
    }
}
