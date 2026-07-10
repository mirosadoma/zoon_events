<?php

use App\Modules\Attendees\Http\Controllers\WalkUpRegistrationController;
use App\Modules\Scanning\Http\Controllers\CheckInSummaryController;
use App\Modules\Scanning\Http\Controllers\ManualDesk\LookupController;
use App\Modules\Scanning\Http\Controllers\OfflineAllowlistController;
use App\Modules\Scanning\Http\Controllers\OfflineScanBatchController;
use App\Modules\Scanning\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant/events/{event_id}')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::post('/scans', [ScanController::class, 'store'])
            ->middleware(['idempotency']);
        Route::get('/check-in-summary', [CheckInSummaryController::class, 'show'])
            ->middleware(['permission:checkin.dashboard.view,tenant']);
        Route::get('/offline-allowlist', [OfflineAllowlistController::class, 'show'])
            ->middleware(['permission:checkin.scan.submit,tenant']);
        Route::post('/offline-scan-batches', [OfflineScanBatchController::class, 'store'])
            ->middleware(['permission:checkin.scan.submit,tenant', 'idempotency']);
        Route::post('/desk/lookups', [LookupController::class, 'store'])
            ->middleware(['permission:checkin.desk.perform,tenant']);
        Route::post('/walk-up-registrations', [WalkUpRegistrationController::class, 'store'])
            ->middleware(['permission:attendee.walkup.register,tenant', 'idempotency']);
    });
