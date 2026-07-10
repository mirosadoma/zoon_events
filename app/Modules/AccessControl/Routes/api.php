<?php

use App\Modules\AccessControl\Http\Controllers\Integration\AccessEventController;
use App\Modules\AccessControl\Http\Controllers\Integration\EmergencyCallbackController;
use App\Modules\AccessControl\Http\Controllers\Integration\GateAuthorizationController;
use App\Modules\AccessControl\Http\Controllers\Management\AcsHealthController;
use App\Modules\AccessControl\Http\Controllers\Management\AcsIntegrationCredentialController;
use App\Modules\AccessControl\Http\Controllers\Management\AcsLaneController;
use App\Modules\AccessControl\Http\Controllers\Management\AcsRuleController;
use App\Modules\AccessControl\Http\Controllers\Management\AcsZoneController;
use App\Modules\AccessControl\Http\Controllers\Management\EmergencyController;
use App\Modules\AccessControl\Http\Controllers\Management\GateEventsController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant/events/{event_id}')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::post('/acs/zones', [AcsZoneController::class, 'store'])
            ->middleware(['idempotency'])
            ->name('api.v1.tenant.acs.zones.store');

        Route::get('/acs/zones', [AcsZoneController::class, 'index'])
            ->name('api.v1.tenant.acs.zones.index');

        Route::patch('/acs/zones/{zone_id}', [AcsZoneController::class, 'update'])
            ->middleware(['idempotency'])
            ->name('api.v1.tenant.acs.zones.update');

        Route::post('/acs/lanes', [AcsLaneController::class, 'store'])
            ->middleware(['idempotency'])
            ->name('api.v1.tenant.acs.lanes.store');

        Route::get('/acs/lanes', [AcsLaneController::class, 'index'])
            ->name('api.v1.tenant.acs.lanes.index');

        Route::post('/acs/rules', [AcsRuleController::class, 'store'])
            ->middleware(['idempotency'])
            ->name('api.v1.tenant.acs.rules.store');

        Route::get('/acs/rules', [AcsRuleController::class, 'index'])
            ->name('api.v1.tenant.acs.rules.index');

        Route::post('/acs/integration-credentials', [AcsIntegrationCredentialController::class, 'store'])
            ->middleware(['idempotency'])
            ->name('api.v1.tenant.acs.integration-credentials.store');

        Route::post('/acs/emergency', [EmergencyController::class, 'store'])
            ->middleware(['idempotency'])
            ->name('api.v1.tenant.acs.emergency.store');

        Route::get('/acs/gate-events', [GateEventsController::class, 'index'])
            ->name('api.v1.tenant.acs.gate-events.index');

        Route::get('/acs/health', [AcsHealthController::class, 'index'])
            ->name('api.v1.tenant.acs.health.index');
    });

Route::prefix('acs/v1')
    ->middleware(['acs.integration.clear', 'acs.integration'])
    ->group(function (): void {
        Route::post('/authorize', [GateAuthorizationController::class, 'store'])
            ->middleware(['acs.capability:authorize', 'idempotency'])
            ->name('api.v1.acs.authorize');

        Route::post('/events', [AccessEventController::class, 'store'])
            ->middleware(['acs.capability:event.ingest', 'idempotency'])
            ->name('api.v1.acs.events.store');

        Route::post('/emergency', [EmergencyCallbackController::class, 'store'])
            ->middleware(['acs.capability:emergency.ingest', 'idempotency'])
            ->name('api.v1.acs.emergency.store');
    });
