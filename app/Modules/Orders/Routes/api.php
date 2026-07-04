<?php

use App\Modules\Orders\Http\Controllers\OrganizerOrderController;
use App\Modules\Orders\Http\Controllers\PublicRegistrationController;
use Illuminate\Support\Facades\Route;

Route::post('public/events/{event_slug}/registrations', [PublicRegistrationController::class, 'store'])
    ->middleware(['throttle:public-registration', 'public.event.context.clear', 'public.event.context']);

Route::get('public/orders/{public_reference}', [PublicRegistrationController::class, 'show'])
    ->middleware('throttle:public-event');

Route::get('tenant/events/{event_id}/orders', [OrganizerOrderController::class, 'index'])
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context', 'permission:order.view,tenant']);

Route::post('tenant/events/{event_id}/orders/{order_id}/cancel', [OrganizerOrderController::class, 'cancel'])
    ->middleware([
        'auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear',
        'tenant.context', 'permission:order.manage,tenant', 'idempotency',
    ]);
