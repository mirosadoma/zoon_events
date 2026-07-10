<?php

namespace App\Modules\Scanning\Providers;

use App\Modules\Audit\Application\Listeners\Phase2\ScanAuditListener;
use App\Modules\Scanning\Application\Actions\ScanDecisionEvaluatorImpl;
use App\Modules\Scanning\Contracts\ScanDecisionEvaluator;
use App\Modules\Scanning\Contracts\ScanEventPersonalDataAnonymizer;
use App\Modules\Scanning\Domain\Events\OfflineScanBatchProcessed;
use App\Modules\Scanning\Domain\Events\OfflineScanBatchReceived;
use App\Modules\Scanning\Domain\Events\OfflineScanConflictFlagged;
use App\Modules\Scanning\Domain\Events\ScanAccepted;
use App\Modules\Scanning\Domain\Events\ScanDuplicate;
use App\Modules\Scanning\Domain\Events\ScanExpired;
use App\Modules\Scanning\Domain\Events\ScanManualOverride;
use App\Modules\Scanning\Domain\Events\ScanRejected;
use App\Modules\Scanning\Domain\Events\ScanRevoked;
use App\Modules\Scanning\Infrastructure\Persistence\DatabaseScanEventPersonalDataAnonymizer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class ScanningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ScanDecisionEvaluator::class, ScanDecisionEvaluatorImpl::class);
        $this->app->bind(ScanEventPersonalDataAnonymizer::class, DatabaseScanEventPersonalDataAnonymizer::class);
    }

    public function boot(): void
    {
        $listener = ScanAuditListener::class;
        Event::listen(ScanAccepted::class, [$listener, 'handleAccepted']);
        Event::listen(ScanManualOverride::class, [$listener, 'handleManualOverride']);
        Event::listen(ScanDuplicate::class, [$listener, 'handleDuplicate']);
        Event::listen(ScanRevoked::class, [$listener, 'handleRevoked']);
        Event::listen(ScanExpired::class, [$listener, 'handleExpired']);
        Event::listen(ScanRejected::class, [$listener, 'handleRejected']);
        Event::listen(OfflineScanBatchReceived::class, [$listener, 'handleOfflineBatchReceived']);
        Event::listen(OfflineScanBatchProcessed::class, [$listener, 'handleOfflineBatchProcessed']);
        Event::listen(OfflineScanConflictFlagged::class, [$listener, 'handleOfflineConflictFlagged']);
    }
}
