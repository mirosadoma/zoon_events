<?php

use App\Modules\EventSites\Http\Controllers\OrganizerEventSiteController;
use App\Modules\EventSites\Http\Controllers\Public\PublicEventSiteController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant/events/{event_id}/site')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::get('/', [OrganizerEventSiteController::class, 'show'])
            ->middleware('permission:event.view,tenant');

        Route::put('/draft', [OrganizerEventSiteController::class, 'saveDraft'])
            ->middleware(['permission:event.manage,tenant', 'idempotency']);

        Route::post('/publish', [OrganizerEventSiteController::class, 'publish'])
            ->middleware(['permission:event.publish,tenant', 'idempotency']);

        Route::post('/unpublish', [OrganizerEventSiteController::class, 'unpublish'])
            ->middleware(['permission:event.publish,tenant', 'idempotency']);

        Route::get('/versions', [OrganizerEventSiteController::class, 'versions'])
            ->middleware('permission:event.view,tenant');

        Route::post('/versions/{version_id}/restore', [OrganizerEventSiteController::class, 'restore'])
            ->middleware(['permission:event.publish,tenant', 'idempotency']);

        Route::post('/media', [OrganizerEventSiteController::class, 'media'])
            ->middleware('permission:event.manage,tenant');

        Route::get('/form-submissions', [OrganizerEventSiteController::class, 'submissions'])
            ->middleware('permission:event.view,tenant');
    });

Route::prefix('public/events/{event_slug}')
    ->middleware(['throttle:public-event', 'public.event.context.clear', 'public.event.context'])
    ->group(function (): void {
        Route::get('/site', [PublicEventSiteController::class, 'show']);
        Route::post('/site/forms/{block_id}', [PublicEventSiteController::class, 'submitForm'])
            ->middleware('throttle:public-registration');
    });
