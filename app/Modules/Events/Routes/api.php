<?php

use App\Modules\Events\Http\Controllers\CategoryTemplateController;
use App\Modules\Events\Http\Controllers\EmailTemplateController;
use App\Modules\Events\Http\Controllers\EventCategoryController;
use App\Modules\Events\Http\Controllers\EventInviteController;
use App\Modules\Events\Http\Controllers\OrganizerAgendaController;
use App\Modules\Events\Http\Controllers\OrganizerEventController;
use App\Modules\Events\Http\Controllers\OrganizerEventPathController;
use App\Modules\Events\Http\Controllers\OrganizerEventVenueController;
use App\Modules\Events\Http\Controllers\OrganizerEventVenueMapController;
use App\Modules\Events\Http\Controllers\OrganizerEventZoneController;
use App\Modules\Events\Http\Controllers\PrivilegeController;
use App\Modules\Events\Http\Controllers\Public\PublicEventController;
use App\Modules\Events\Http\Controllers\Public\PublicEventVenueMapController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant/events')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::get('/', [OrganizerEventController::class, 'index'])->middleware('permission:event.view,tenant');
        Route::post('/', [OrganizerEventController::class, 'store'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::get('/{event_id}', [OrganizerEventController::class, 'show'])->middleware('permission:event.view,tenant');
        Route::patch('/{event_id}', [OrganizerEventController::class, 'update'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::post('/{event_id}/publish', [OrganizerEventController::class, 'publish'])->middleware(['permission:event.publish,tenant', 'idempotency']);
        Route::post('/{event_id}/unpublish', [OrganizerEventController::class, 'unpublish'])->middleware(['permission:event.publish,tenant', 'idempotency']);
        Route::post('/{event_id}/cancel', [OrganizerEventController::class, 'cancel'])->middleware(['permission:event.cancel,tenant', 'idempotency']);
        Route::post('/{event_id}/reopen', [OrganizerEventController::class, 'reopen'])->middleware(['permission:event.reopen,tenant', 'idempotency']);
        Route::post('/{event_id}/archive', [OrganizerEventController::class, 'archive'])->middleware(['permission:event.archive,tenant', 'idempotency']);
        Route::post('/{event_id}/copy', [OrganizerEventController::class, 'copy'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::put('/{event_id}/agenda', [OrganizerAgendaController::class, 'sync'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::put('/{event_id}/venues', [OrganizerEventVenueController::class, 'sync'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::put('/{event_id}/zones', [OrganizerEventZoneController::class, 'sync'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::post('/{event_id}/venues/{venue_id}/zones/{zone_id}/fill-image', [OrganizerEventZoneController::class, 'storeFillImage'])->middleware('permission:event.manage,tenant');
        Route::delete('/{event_id}/venues/{venue_id}/zones/{zone_id}/fill-image', [OrganizerEventZoneController::class, 'destroyFillImage'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::put('/{event_id}/paths', [OrganizerEventPathController::class, 'sync'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::get('/{event_id}/venues/{venue_id}/map', [OrganizerEventVenueMapController::class, 'show'])->middleware('permission:event.view,tenant');
        Route::post('/{event_id}/venues/{venue_id}/map', [OrganizerEventVenueMapController::class, 'store'])->middleware('permission:event.manage,tenant');
        Route::patch('/{event_id}/venues/{venue_id}/map/settings', [OrganizerEventVenueMapController::class, 'updateSettings'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::delete('/{event_id}/venues/{venue_id}/map', [OrganizerEventVenueMapController::class, 'destroy'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::get('/{event_id}/venues/{venue_id}/zones', [OrganizerEventVenueMapController::class, 'zones'])->middleware('permission:event.view,tenant');

        Route::get('/{event_id}/invites/template', [EventInviteController::class, 'template'])->middleware('permission:event.invite.manage,tenant');
        Route::post('/{event_id}/invites', [EventInviteController::class, 'send'])->middleware(['permission:event.invite.manage,tenant', 'idempotency']);
        Route::post('/{event_id}/invites/bulk', [EventInviteController::class, 'sendBulk'])->middleware(['permission:event.invite.manage,tenant', 'idempotency']);
        Route::delete('/{event_id}/invites/{invite_id}', [EventInviteController::class, 'destroy'])->middleware(['permission:event.invite.manage,tenant', 'idempotency']);

        // Event category assignments
        Route::get('/{event_id}/categories', [EventCategoryController::class, 'index'])->middleware('permission:category.view,tenant');
        Route::put('/{event_id}/categories/assignments', [EventCategoryController::class, 'sync'])->middleware(['permission:category.manage,tenant', 'idempotency']);
        Route::delete('/{event_id}/categories/{category_id}', [EventCategoryController::class, 'destroy'])->middleware(['permission:category.manage,tenant', 'idempotency']);

        // Email templates
        Route::post('/{event_id}/email-templates/images', [EmailTemplateController::class, 'uploadImage'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::get('/{event_id}/email-templates/{type}', [EmailTemplateController::class, 'show'])->middleware('permission:event.manage,tenant');
        Route::put('/{event_id}/email-templates/{type}', [EmailTemplateController::class, 'update'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::delete('/{event_id}/email-templates/{type}', [EmailTemplateController::class, 'destroy'])->middleware(['permission:event.manage,tenant', 'idempotency']);
    });

// Privileges catalog (tenant-level)
Route::prefix('tenant/privileges')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::get('/', [PrivilegeController::class, 'index'])->middleware('permission:privilege.view,tenant');
        Route::post('/', [PrivilegeController::class, 'store'])->middleware(['permission:privilege.manage,tenant', 'idempotency']);
        Route::get('/{privilege_id}', [PrivilegeController::class, 'show'])->middleware('permission:privilege.view,tenant');
        Route::patch('/{privilege_id}', [PrivilegeController::class, 'update'])->middleware(['permission:privilege.manage,tenant', 'idempotency']);
        Route::delete('/{privilege_id}', [PrivilegeController::class, 'destroy'])->middleware(['permission:privilege.manage,tenant', 'idempotency']);
    });

// Category Templates (tenant-level)
Route::prefix('tenant/category-templates')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::get('/', [CategoryTemplateController::class, 'index'])->middleware('permission:category.view,tenant');
        Route::post('/', [CategoryTemplateController::class, 'store'])->middleware(['permission:category.manage,tenant', 'idempotency']);
        Route::get('/{template_id}', [CategoryTemplateController::class, 'show'])->middleware('permission:category.view,tenant');
        Route::patch('/{template_id}', [CategoryTemplateController::class, 'update'])->middleware(['permission:category.manage,tenant', 'idempotency']);
        Route::delete('/{template_id}', [CategoryTemplateController::class, 'destroy'])->middleware(['permission:category.manage,tenant', 'idempotency']);
    });

Route::prefix('public/events/{event_slug}')
    ->middleware(['throttle:public-event', 'public.event.context.clear', 'public.event.context'])
    ->group(function (): void {
        Route::get('/', [PublicEventController::class, 'show']);
        Route::get('/registration-form', [PublicEventController::class, 'form'])->middleware('throttle:public-registration');
        Route::get('/venues/{venue_id}/map', [PublicEventVenueMapController::class, 'show']);
    });
