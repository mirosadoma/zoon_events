<?php

use App\Modules\BadgePrinting\Http\Controllers\BadgePrintJobController;
use App\Modules\BadgePrinting\Http\Controllers\BadgeTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant/events/{event_id}')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::get('/badge-print-jobs', [BadgePrintJobController::class, 'index'])
            ->middleware(['permission:badge.print,tenant']);

        Route::post('/badge-print-jobs', [BadgePrintJobController::class, 'store'])
            ->middleware(['permission:badge.print,tenant', 'idempotency']);
        Route::post('/badge-print-jobs/{badge_print_job_id}/reprint', [BadgePrintJobController::class, 'reprint'])
            ->middleware(['permission:badge.reprint,tenant', 'idempotency']);

        Route::middleware(['permission:badge.template.manage,tenant'])->group(function (): void {
            Route::get('/badge-templates', [BadgeTemplateController::class, 'index']);
            Route::post('/badge-templates', [BadgeTemplateController::class, 'store'])
                ->middleware('idempotency');
            Route::patch('/badge-templates/{template_id}', [BadgeTemplateController::class, 'update'])
                ->middleware('idempotency');
            Route::post('/badge-templates/{template_id}/activate', [BadgeTemplateController::class, 'activate'])
                ->middleware('idempotency');
            Route::post('/badge-templates/{template_id}/deactivate', [BadgeTemplateController::class, 'deactivate'])
                ->middleware('idempotency');
        });
    });
