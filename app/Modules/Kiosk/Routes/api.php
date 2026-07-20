<?php

use App\Modules\Kiosk\Http\Controllers\Device\KioskBadgePrintController;
use App\Modules\Kiosk\Http\Controllers\Device\KioskHeartbeatController;
use App\Modules\Kiosk\Http\Controllers\Device\KioskLookupController;
use App\Modules\Kiosk\Http\Controllers\Device\KioskScanController;
use App\Modules\Kiosk\Http\Controllers\Device\KioskSessionConfirmationController;
use App\Modules\Kiosk\Http\Controllers\Management\KioskController;
use App\Modules\Kiosk\Http\Controllers\Management\KioskPairingController;
use Illuminate\Support\Facades\Route;

// Kiosk Management (staff)
Route::prefix('tenant/events/{event_id}')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::post('/kiosks', [KioskController::class, 'store'])
            ->middleware(['permission:kiosk.manage,tenant', 'idempotency'])
            ->name('api.v1.tenant.kiosks.store');

        Route::get('/kiosks', [KioskController::class, 'index'])
            ->middleware(['permission:kiosk.health.view,tenant'])
            ->name('api.v1.tenant.kiosks.index');

        Route::get('/kiosks/{kiosk_id}', [KioskController::class, 'show'])
            ->middleware(['permission:kiosk.health.view,tenant'])
            ->name('api.v1.tenant.kiosks.show');

        Route::post('/kiosks/{kiosk_id}/retire', [KioskController::class, 'retire'])
            ->middleware(['permission:kiosk.manage,tenant', 'idempotency'])
            ->name('api.v1.tenant.kiosks.retire');

        Route::post('/kiosks/{kiosk_id}/pair', [KioskPairingController::class, 'store'])
            ->middleware(['permission:kiosk.manage,tenant', 'idempotency'])
            ->name('api.v1.tenant.kiosks.pair');
    });

// Kiosk Device routes (device-session auth, no Sanctum)
Route::prefix('kiosk/v1')
    ->middleware(['kiosk.session.clear', 'kiosk.session'])
    ->group(function (): void {
        Route::post('/heartbeat', [KioskHeartbeatController::class, 'store'])
            ->name('api.v1.kiosk.heartbeat');

        Route::post('/session/confirm', [KioskSessionConfirmationController::class, 'store'])
            ->name('api.v1.kiosk.session.confirm');

        Route::post('/lookups', [KioskLookupController::class, 'store'])
            ->name('api.v1.kiosk.lookups');

        Route::post('/scans', [KioskScanController::class, 'store'])
            ->middleware(['idempotency'])
            ->name('api.v1.kiosk.scans');

        Route::post('/badge-print-jobs/preview', [KioskBadgePrintController::class, 'preview'])
            ->name('api.v1.kiosk.badge-print-jobs.preview');

        Route::post('/badge-print-jobs', [KioskBadgePrintController::class, 'store'])
            ->middleware(['idempotency'])
            ->name('api.v1.kiosk.badge-print-jobs');
    });
