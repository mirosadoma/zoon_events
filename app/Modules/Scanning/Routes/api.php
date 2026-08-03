<?php

use App\Modules\Attendees\Http\Controllers\WalkUpRegistrationController;
use App\Modules\Scanning\Http\Controllers\CheckInSummaryController;
use App\Modules\Scanning\Http\Controllers\ManualDesk\LookupController;
use App\Modules\Scanning\Http\Controllers\OfflineAllowlistController;
use App\Modules\Scanning\Http\Controllers\OfflineScanBatchController;
use App\Modules\Scanning\Http\Controllers\ScanController;
use App\Modules\Scanning\Http\Controllers\ScannerApp\ScannerAppAuthController;
use App\Modules\Scanning\Http\Controllers\ScannerApp\ScannerAppScanController;
use App\Modules\Scanning\Http\Controllers\ZoneOccupancyController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant/events/{event_id}')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::post('/scans', [ScanController::class, 'store'])
            ->middleware(['idempotency']);
        Route::get('/check-in-summary', [CheckInSummaryController::class, 'show'])
            ->middleware(['permission:checkin.dashboard.view,tenant']);
        Route::get('/zone-occupancy/summary', [ZoneOccupancyController::class, 'summary'])
            ->middleware(['permission:checkin.dashboard.view,tenant'])
            ->name('api.v1.tenant.zone-occupancy.summary');
        Route::get('/zone-occupancy/analytics', [ZoneOccupancyController::class, 'analytics'])
            ->middleware(['permission:checkin.dashboard.view,tenant'])
            ->name('api.v1.tenant.zone-occupancy.analytics');
        Route::get('/offline-allowlist', [OfflineAllowlistController::class, 'show'])
            ->middleware(['permission:checkin.scan.submit,tenant']);
        Route::post('/offline-scan-batches', [OfflineScanBatchController::class, 'store'])
            ->middleware(['permission:checkin.scan.submit,tenant', 'idempotency']);
        Route::post('/desk/lookups', [LookupController::class, 'store'])
            ->middleware(['permission:checkin.desk.perform,tenant']);
        Route::post('/walk-up-registrations', [WalkUpRegistrationController::class, 'store'])
            ->middleware(['permission:attendee.walkup.register,tenant', 'idempotency']);
    });

Route::prefix('scanner-app')
    ->middleware(['throttle:phase1-organizer', 'scanner.app.clear'])
    ->group(function (): void {
        Route::post('/login', [ScannerAppAuthController::class, 'login'])
            ->name('api.v1.scanner-app.login');

        Route::middleware(['scanner.app'])->group(function (): void {
            Route::get('/me', [ScannerAppAuthController::class, 'me'])
                ->name('api.v1.scanner-app.me');
            Route::post('/logout', [ScannerAppAuthController::class, 'logout'])
                ->name('api.v1.scanner-app.logout');
            Route::post('/scan', [ScannerAppScanController::class, 'store'])
                ->middleware(['idempotency'])
                ->name('api.v1.scanner-app.scan');
        });
    });
