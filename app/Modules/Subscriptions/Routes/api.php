<?php

use App\Modules\Subscriptions\Http\Controllers\OrganizerSubscribeController;
use App\Modules\Subscriptions\Http\Controllers\SubscriptionPlanController;
use App\Modules\Subscriptions\Http\Controllers\TenantSubscriptionController;
use Illuminate\Support\Facades\Route;

// Public (no auth required) — for organizer registration page
Route::get('/subscription-plans', [SubscriptionPlanController::class, 'publicIndex'])
    ->name('api.v1.subscription-plans.public');

Route::post('/subscribe', OrganizerSubscribeController::class)
    ->middleware('throttle:auth')
    ->name('api.v1.subscribe');

Route::middleware('auth:sanctum')->group(function (): void {
    // Platform admin CRUD for subscription plans
    Route::prefix('platform/subscription-plans')
        ->middleware(['throttle:platform', 'bindings', 'permission:platform.subscription.manage,platform'])
        ->group(function (): void {
            Route::get('/', [SubscriptionPlanController::class, 'index'])->name('api.v1.platform.subscription-plans.index');
            Route::post('/', [SubscriptionPlanController::class, 'store'])->middleware('idempotency')->name('api.v1.platform.subscription-plans.store');
            Route::get('/{plan}', [SubscriptionPlanController::class, 'show'])->name('api.v1.platform.subscription-plans.show');
            Route::patch('/{plan}', [SubscriptionPlanController::class, 'update'])->middleware('idempotency')->name('api.v1.platform.subscription-plans.update');
            Route::delete('/{plan}', [SubscriptionPlanController::class, 'destroy'])->middleware('idempotency')->name('api.v1.platform.subscription-plans.destroy');
        });

    // Platform admin subscription renewal
    Route::post('/platform/subscriptions/{subscription}/renew', [TenantSubscriptionController::class, 'renew'])
        ->middleware(['throttle:platform', 'bindings', 'permission:platform.subscription.manage,platform', 'idempotency'])
        ->name('api.v1.platform.subscriptions.renew');

    // Tenant subscription view
    Route::prefix('tenant/subscriptions')
        ->middleware(['throttle:tenant', 'tenant.context.clear', 'tenant.context', 'permission:subscription.view,tenant'])
        ->group(function (): void {
            Route::get('/', [TenantSubscriptionController::class, 'index'])->name('api.v1.tenant.subscriptions.index');
            Route::get('/current', [TenantSubscriptionController::class, 'current'])->name('api.v1.tenant.subscriptions.current');
        });
});
