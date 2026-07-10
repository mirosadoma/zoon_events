<?php

use App\Modules\Payments\Http\Controllers\OrganizerRefundController;
use App\Modules\Payments\Http\Controllers\PublicPaymentIntentController;
use App\Modules\Payments\Http\Controllers\Webhooks\MoyasarWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('public/orders/{public_reference}/payment-intents', [PublicPaymentIntentController::class, 'store'])
    ->middleware('throttle:public-checkout');

Route::post('webhooks/payments/moyasar/{route_token}', MoyasarWebhookController::class)
    ->middleware('throttle:payment-callback');

Route::post('tenant/events/{event_id}/orders/{order_id}/refunds', [OrganizerRefundController::class, 'store'])
    ->middleware([
        'auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear',
        'tenant.context', 'permission:payment.refund,tenant', 'idempotency',
    ]);
