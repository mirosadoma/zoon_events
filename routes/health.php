<?php

use App\Modules\Operations\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/health')->group(function (): void {
    Route::get('/live', [HealthController::class, 'live'])->name('health.live');
    Route::get('/ready', [HealthController::class, 'ready'])->name('health.ready');
});
