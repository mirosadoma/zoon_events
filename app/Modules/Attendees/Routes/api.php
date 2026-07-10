<?php

use App\Modules\Attendees\Http\Controllers\OrganizerAttendeeController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant/events/{event_id}/attendees')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::get('/', [OrganizerAttendeeController::class, 'index'])->middleware('permission:attendee.view,tenant');
        Route::patch('/{attendee_id}', [OrganizerAttendeeController::class, 'update'])
            ->middleware(['permission:attendee.manage,tenant', 'idempotency']);
    });
