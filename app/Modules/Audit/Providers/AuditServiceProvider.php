<?php

namespace App\Modules\Audit\Providers;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Audit\Application\Integrity\AuditIntegrityService;
use App\Modules\Audit\Application\Integrity\CanonicalAuditPayload;
use App\Modules\Audit\Contracts\AuditWriter as AuditWriterContract;
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
}
