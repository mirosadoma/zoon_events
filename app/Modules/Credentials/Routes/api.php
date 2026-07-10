<?php

use App\Modules\Credentials\Http\Controllers\CredentialLifecycleController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant')
    ->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::post('events/{event_id}/credentials/{credential_id}/revoke', [CredentialLifecycleController::class, 'revoke'])
            ->middleware(['permission:credential.revoke,tenant', 'idempotency']);
        Route::post('events/{event_id}/credentials/{credential_id}/reissue', [CredentialLifecycleController::class, 'reissue'])
            ->middleware(['permission:credential.reissue,tenant', 'idempotency']);
        Route::post('credential-validations', [CredentialLifecycleController::class, 'validateCredential'])
            ->middleware('permission:credential.validate,tenant');
    });
