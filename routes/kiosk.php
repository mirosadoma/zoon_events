<?php

use App\Modules\Kiosk\Http\Controllers\Device\KioskAttendeesController;
use App\Modules\Kiosk\Http\Controllers\Device\KioskBadgePrintController;
use App\Modules\Kiosk\Http\Controllers\Device\KioskHeartbeatController;
use App\Modules\Kiosk\Http\Controllers\Device\KioskLookupController;
use App\Modules\Kiosk\Http\Controllers\Device\KioskScanController;
use App\Modules\Kiosk\Http\Controllers\Device\KioskSessionConfirmationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kiosk device API (desktop sync) — prefix /kiosk/v1
|--------------------------------------------------------------------------
|
| Preferred surface for zonetec_kiosk. Legacy alias remains at
| /api/v1/kiosk/v1 via Modules/Kiosk/Routes/api.php.
|
*/

Route::prefix('kiosk/v1')
    ->middleware(['api', 'kiosk.session.clear', 'kiosk.session'])
    ->group(function (): void {
        Route::post('/heartbeat', [KioskHeartbeatController::class, 'store'])
            ->name('kiosk.v1.heartbeat');

        Route::post('/session/confirm', [KioskSessionConfirmationController::class, 'store'])
            ->name('kiosk.v1.session.confirm');

        Route::post('/lookups', [KioskLookupController::class, 'store'])
            ->name('kiosk.v1.lookups');

        Route::get('/attendees', [KioskAttendeesController::class, 'index'])
            ->name('kiosk.v1.attendees');

        Route::post('/scans', [KioskScanController::class, 'store'])
            ->middleware(['idempotency'])
            ->name('kiosk.v1.scans');

        Route::post('/badge-print-jobs/preview', [KioskBadgePrintController::class, 'preview'])
            ->name('kiosk.v1.badge-print-jobs.preview');

        Route::post('/badge-print-jobs', [KioskBadgePrintController::class, 'store'])
            ->middleware(['idempotency'])
            ->name('kiosk.v1.badge-print-jobs');
    });
