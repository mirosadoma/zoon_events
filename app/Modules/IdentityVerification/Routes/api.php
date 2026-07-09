<?php

use App\Modules\IdentityVerification\Http\Controllers\AttendeeIdentityController;
use App\Modules\IdentityVerification\Http\Controllers\ComplianceController;
use App\Modules\IdentityVerification\Http\Controllers\RequirementsController;
use App\Modules\IdentityVerification\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant/events/{event_id}')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::get('/identity/requirements', [RequirementsController::class, 'index'])
            ->middleware('permission:identity.configure,tenant')
            ->name('api.v1.tenant.identity.requirements.index');
        Route::put('/identity/requirements', [RequirementsController::class, 'update'])
            ->middleware(['permission:identity.configure,tenant', 'idempotency'])
            ->name('api.v1.tenant.identity.requirements.update');
        Route::get('/identity/review', [ReviewController::class, 'index'])
            ->middleware('permission:identity.review,tenant')
            ->name('api.v1.tenant.identity.review.index');
        Route::post('/identity/verifications/{verification_id}/review', [ReviewController::class, 'store'])
            ->middleware(['permission:identity.review,tenant', 'idempotency'])
            ->name('api.v1.tenant.identity.review.store');
        Route::get('/attendees/{attendee_id}/identity/data', [ComplianceController::class, 'show'])
            ->middleware('permission:identity.data.view,tenant')
            ->name('api.v1.tenant.identity.data.show');
        Route::delete('/attendees/{attendee_id}/identity/data', [ComplianceController::class, 'destroy'])
            ->middleware(['permission:identity.data.manage,tenant', 'idempotency'])
            ->name('api.v1.tenant.identity.data.destroy');
    });

Route::prefix('tenant/events/{event_id}/attendees/{attendee_id}/identity')
    ->middleware('throttle:public-event')
    ->group(function (): void {
        Route::post('/consent', [AttendeeIdentityController::class, 'storeConsent'])
            ->name('api.v1.tenant.identity.consent.store');
        Route::delete('/consent', [AttendeeIdentityController::class, 'destroyConsent'])
            ->name('api.v1.tenant.identity.consent.destroy');
        Route::get('/verification', [AttendeeIdentityController::class, 'showVerification'])
            ->name('api.v1.tenant.identity.verification.show');
        Route::post('/verification', [AttendeeIdentityController::class, 'startVerification'])
            ->name('api.v1.tenant.identity.verification.start');
        Route::post('/face-capture', [AttendeeIdentityController::class, 'storeFaceCapture'])
            ->name('api.v1.tenant.identity.face_capture.store');
    });

Route::post('identity/providers/government/callback', [AttendeeIdentityController::class, 'governmentCallback'])
    ->middleware('throttle:public-event')
    ->name('api.v1.identity.government.callback');
