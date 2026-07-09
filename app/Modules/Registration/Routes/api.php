<?php

use App\Modules\Registration\Http\Controllers\OrganizerRegistrationFormController;
use App\Modules\Registration\Http\Controllers\PreviewRegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant/events/{event_id}/registration-form')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context', 'permission:registration.manage,tenant'])
    ->group(function (): void {
        Route::put('/', [OrganizerRegistrationFormController::class, 'save'])->middleware('idempotency');
        Route::post('/publish', [OrganizerRegistrationFormController::class, 'publish'])->middleware('idempotency');
    });

Route::post('tenant/events/{event_id}/registration-preview', [PreviewRegistrationController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context', 'permission:registration.manage,tenant', 'idempotency']);
