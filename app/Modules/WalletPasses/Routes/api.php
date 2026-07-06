<?php

use App\Modules\WalletPasses\Http\Controllers\AppleWebService\RegisterDeviceController;
use App\Modules\WalletPasses\Http\Controllers\AppleWebService\UnregisterDeviceController;
use App\Modules\WalletPasses\Http\Controllers\AppleWebService\UpdatedPassController;
use App\Modules\WalletPasses\Http\Controllers\AppleWebService\UpdatedSerialNumbersController;
use App\Modules\WalletPasses\Http\Controllers\Public\ApplePassController;
use App\Modules\WalletPasses\Http\Controllers\Public\GoogleWalletPassController;
use Illuminate\Support\Facades\Route;

Route::get('public/orders/{public_reference}/wallet-passes/apple', [ApplePassController::class, 'show'])
    ->middleware('throttle:public-wallet');
Route::get('public/orders/{public_reference}/wallet-passes/google', [GoogleWalletPassController::class, 'show'])
    ->middleware('throttle:public-wallet');

Route::prefix('wallet/apple/v1')
    ->middleware('throttle:apple-wallet-webservice')
    ->group(function (): void {
        Route::post('devices/{device_library_identifier}/registrations/{pass_type_identifier}/{serial_number}', [RegisterDeviceController::class, 'store'])
            ->where('pass_type_identifier', '[^/]+')
            ->middleware('apple-pass-auth');
        Route::delete('devices/{device_library_identifier}/registrations/{pass_type_identifier}/{serial_number}', [UnregisterDeviceController::class, 'destroy'])
            ->where('pass_type_identifier', '[^/]+')
            ->middleware('apple-pass-auth');
        Route::get('devices/{device_library_identifier}/registrations/{pass_type_identifier}', [UpdatedSerialNumbersController::class, 'index'])
            ->where('pass_type_identifier', '[^/]+');
        Route::get('passes/{pass_type_identifier}/{serial_number}', [UpdatedPassController::class, 'show'])
            ->where('pass_type_identifier', '[^/]+')
            ->middleware('apple-pass-auth');
    });
