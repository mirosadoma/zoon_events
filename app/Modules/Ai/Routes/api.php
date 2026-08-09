<?php

use App\Modules\Ai\Http\Controllers\AiInsightController;
use App\Modules\Ai\Http\Controllers\AssistantConfigController;
use App\Modules\Ai\Http\Controllers\AssistantFaqController;
use App\Modules\Ai\Http\Controllers\PlatformChatController;
use App\Modules\Ai\Http\Controllers\Public\PublicAssistantController;
use Illuminate\Support\Facades\Route;

Route::post('/chat', PlatformChatController::class)
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context', 'permission:reports.view,tenant']);

Route::prefix('tenant/events/{event_id}/assistant')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::get('/', [AssistantConfigController::class, 'show'])->middleware('permission:event.view,tenant');
        Route::put('/', [AssistantConfigController::class, 'update'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::post('/reindex', [AssistantConfigController::class, 'reindex'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::get('/usage', [AssistantConfigController::class, 'usage'])->middleware('permission:reports.view,tenant');
        Route::delete('/conversations/{public_id}', [AssistantConfigController::class, 'deleteConversation'])->middleware(['permission:event.manage,tenant', 'idempotency']);

        Route::get('/faqs', [AssistantFaqController::class, 'index'])->middleware('permission:event.view,tenant');
        Route::post('/faqs', [AssistantFaqController::class, 'store'])->middleware(['permission:event.manage,tenant', 'idempotency']);
        Route::put('/faqs/{faq_id}', [AssistantFaqController::class, 'update'])->middleware(['permission:event.manage,tenant', 'idempotency'])->where('faq_id', '[0-9]+');
        Route::delete('/faqs/{faq_id}', [AssistantFaqController::class, 'destroy'])->middleware(['permission:event.manage,tenant', 'idempotency'])->where('faq_id', '[0-9]+');
    });

Route::prefix('tenant/events/{event_id}/ai-insights')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::post('/', [AiInsightController::class, 'generate'])->middleware('permission:reports.view,tenant');
        Route::post('/ask', [AiInsightController::class, 'ask'])->middleware('permission:reports.view,tenant');
    });

Route::prefix('public/events/{event_slug}/assistant')
    ->middleware(['throttle:public-event', 'throttle:public-assistant', 'public.event.context.clear', 'public.event.context'])
    ->group(function (): void {
        Route::post('/ask', [PublicAssistantController::class, 'ask']);
    });
