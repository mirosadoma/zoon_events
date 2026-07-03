<?php

use App\Modules\AdminConsole\Http\Controllers\Auth\SessionController;
use App\Modules\AdminConsole\Http\Controllers\DashboardController;
use App\Modules\Operations\Http\Controllers\ApiDocsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:auth');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/platform/{section}', [DashboardController::class, 'section'])->name('dashboard.platform.section');
    Route::get('/docs/api/openapi.yaml', ApiDocsController::class)->name('api.docs');
});
