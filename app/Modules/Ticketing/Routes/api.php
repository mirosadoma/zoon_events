<?php

use App\Modules\Ticketing\Http\Controllers\OrganizerPriceTierController;
use App\Modules\Ticketing\Http\Controllers\OrganizerTicketTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant/events/{event_id}/ticket-types')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::get('/', [OrganizerTicketTypeController::class, 'index'])->middleware('permission:event.view,tenant');
        Route::post('/', [OrganizerTicketTypeController::class, 'store'])->middleware(['permission:ticketing.manage,tenant', 'idempotency']);
        Route::patch('/{ticket_type_id}', [OrganizerTicketTypeController::class, 'update'])->middleware(['permission:ticketing.manage,tenant', 'idempotency']);
        Route::post('/{ticket_type_id}/price-tiers', [OrganizerPriceTierController::class, 'store'])->middleware(['permission:ticketing.manage,tenant', 'idempotency']);
        Route::patch('/{ticket_type_id}/price-tiers/{price_tier_id}', [OrganizerPriceTierController::class, 'update'])->middleware(['permission:ticketing.manage,tenant', 'idempotency']);
    });
