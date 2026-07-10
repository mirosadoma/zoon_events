<?php

use App\Modules\Notifications\Http\Controllers\Webhooks\UnifonicCallbackController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/notifications/unifonic/{route_token}', UnifonicCallbackController::class)
    ->middleware('throttle:notification-callback');
