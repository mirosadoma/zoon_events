<?php

use App\Modules\Registration\Http\Controllers\OrganizerRegistrationFormController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant/events/{event_id}/registration-form')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context', 'permission:registration.manage,tenant'])
    ->group(function (): void {
        Route::put('/', [OrganizerRegistrationFormController::class, 'save'])->middleware('idempotency');
        Route::post('/publish', [OrganizerRegistrationFormController::class, 'publish'])->middleware('idempotency');
    });
