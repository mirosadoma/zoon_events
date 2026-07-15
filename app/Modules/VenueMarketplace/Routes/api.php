<?php

use App\Modules\VenueMarketplace\Http\Controllers\MarketplaceCatalogController;
use App\Modules\VenueMarketplace\Http\Controllers\MarketplaceQuoteController;
use App\Modules\VenueMarketplace\Http\Controllers\OwnerVenueAssetController;
use App\Modules\VenueMarketplace\Http\Controllers\OwnerVenueController;
use App\Modules\VenueMarketplace\Http\Controllers\ParticipantDelegationController;
use App\Modules\VenueMarketplace\Http\Controllers\ParticipantRentalController;
use App\Modules\VenueMarketplace\Http\Controllers\ParticipantStatementController;
use App\Modules\VenueMarketplace\Http\Controllers\PlatformMarketplaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant')
    ->middleware(['auth:sanctum', 'throttle:tenant', 'tenant.context.clear', 'tenant.context'])
    ->group(function (): void {
        Route::prefix('marketplace')->group(function (): void {
            if (app()->environment('testing')) {
                Route::get('/__probe/boot', fn () => response()->json(['ok' => true]))
                    ->name('api.v1.tenant.marketplace.__probe.boot');
            }
            Route::get('/catalog', [MarketplaceCatalogController::class, 'index'])
                ->middleware('permission:marketplace.manage,tenant')
                ->name('api.v1.tenant.marketplace.catalog.index');
            Route::get('/catalog/{publication_public_id}', [MarketplaceCatalogController::class, 'show'])
                ->middleware('permission:marketplace.manage,tenant')
                ->name('api.v1.tenant.marketplace.catalog.show');
            Route::post('/quotes', [MarketplaceQuoteController::class, 'store'])
                ->middleware('permission:marketplace.manage,tenant')
                ->name('api.v1.tenant.marketplace.quotes.store');
            Route::get('/rentals', [ParticipantRentalController::class, 'index'])
                ->middleware('permission:marketplace.manage,tenant')
                ->name('api.v1.tenant.marketplace.rentals.index');
            Route::post('/rentals', [ParticipantRentalController::class, 'store'])
                ->middleware(['permission:marketplace.manage,tenant', 'idempotency'])
                ->name('api.v1.tenant.marketplace.rentals.store');
            Route::get('/rentals/{rental_public_id}', [ParticipantRentalController::class, 'show'])
                ->middleware('permission:marketplace.manage,tenant')
                ->name('api.v1.tenant.marketplace.rentals.show');
            Route::post('/rentals/{rental_public_id}/approve', [ParticipantRentalController::class, 'approve'])
                ->middleware(['permission:rentals.approve,tenant', 'idempotency'])
                ->name('api.v1.tenant.marketplace.rentals.approve');
            Route::post('/rentals/{rental_public_id}/reject', [ParticipantRentalController::class, 'reject'])
                ->middleware(['permission:rentals.approve,tenant', 'idempotency'])
                ->name('api.v1.tenant.marketplace.rentals.reject');
            Route::post('/rentals/{rental_public_id}/cancel', [ParticipantRentalController::class, 'cancel'])
                ->middleware(['permission:marketplace.manage,tenant', 'idempotency'])
                ->name('api.v1.tenant.marketplace.rentals.cancel');
            Route::post('/rentals/{rental_public_id}/revoke', [ParticipantRentalController::class, 'revoke'])
                ->middleware(['permission:rentals.approve,tenant', 'idempotency'])
                ->name('api.v1.tenant.marketplace.rentals.revoke');
            Route::get('/rentals/{rental_public_id}/delegation', [ParticipantDelegationController::class, 'show'])
                ->middleware('permission:marketplace.manage,tenant')
                ->name('api.v1.tenant.marketplace.rentals.delegation.show');

            Route::get('/statements', [ParticipantStatementController::class, 'index'])
                ->middleware('permission:reports.view,tenant')
                ->name('api.v1.tenant.marketplace.statements.index');
            Route::get('/statements/{statement_public_id}', [ParticipantStatementController::class, 'show'])
                ->middleware('permission:reports.view,tenant')
                ->name('api.v1.tenant.marketplace.statements.show');
            Route::get('/statements/{statement_public_id}/export', [ParticipantStatementController::class, 'export'])
                ->middleware('permission:reports.view,tenant')
                ->name('api.v1.tenant.marketplace.statements.export');
            Route::post('/statements/{statement_public_id}/disputes', [ParticipantStatementController::class, 'openDispute'])
                ->middleware(['permission:reports.view,tenant', 'idempotency'])
                ->name('api.v1.tenant.marketplace.statements.disputes.store');
            Route::get('/disputes/{dispute_public_id}', [ParticipantStatementController::class, 'showDispute'])
                ->middleware('permission:reports.view,tenant')
                ->name('api.v1.tenant.marketplace.disputes.show');
        });

        Route::prefix('venues')->group(function (): void {
            Route::get('/', [OwnerVenueController::class, 'index'])
                ->middleware('permission:venue.manage,tenant')
                ->name('api.v1.tenant.venues.index');
            Route::post('/', [OwnerVenueController::class, 'store'])
                ->middleware(['permission:venue.manage,tenant', 'idempotency'])
                ->name('api.v1.tenant.venues.store');
            Route::get('/{venue_public_id}', [OwnerVenueController::class, 'show'])
                ->middleware('permission:venue.manage,tenant')
                ->name('api.v1.tenant.venues.show');
            Route::patch('/{venue_public_id}', [OwnerVenueController::class, 'update'])
                ->middleware(['permission:venue.manage,tenant', 'idempotency'])
                ->name('api.v1.tenant.venues.update');
            Route::post('/{venue_public_id}/archive', [OwnerVenueController::class, 'archive'])
                ->middleware(['permission:venue.manage,tenant', 'idempotency'])
                ->name('api.v1.tenant.venues.archive');
            Route::post('/{venue_public_id}/status', [OwnerVenueController::class, 'changeStatus'])
                ->middleware(['permission:venue.manage,tenant', 'idempotency'])
                ->name('api.v1.tenant.venues.status');

            Route::get('/{venue_public_id}/assets', [OwnerVenueAssetController::class, 'index'])
                ->middleware('permission:venue.manage,tenant')
                ->name('api.v1.tenant.venue-assets.index');
            Route::post('/{venue_public_id}/assets', [OwnerVenueAssetController::class, 'store'])
                ->middleware(['permission:venue.manage,tenant', 'idempotency'])
                ->name('api.v1.tenant.venue-assets.store');
            Route::get('/{venue_public_id}/assets/{asset_public_id}', [OwnerVenueAssetController::class, 'show'])
                ->middleware('permission:venue.manage,tenant')
                ->name('api.v1.tenant.venue-assets.show');
            Route::patch('/{venue_public_id}/assets/{asset_public_id}', [OwnerVenueAssetController::class, 'update'])
                ->middleware(['permission:venue.manage,tenant', 'idempotency'])
                ->name('api.v1.tenant.venue-assets.update');
            Route::put('/{venue_public_id}/assets/{asset_public_id}/availability', [OwnerVenueAssetController::class, 'replaceAvailability'])
                ->middleware(['permission:venue.manage,tenant', 'idempotency'])
                ->name('api.v1.tenant.venue-assets.availability.replace');
            Route::post('/{venue_public_id}/assets/{asset_public_id}/publication', [OwnerVenueAssetController::class, 'publish'])
                ->middleware(['permission:venue.manage,tenant', 'idempotency'])
                ->name('api.v1.tenant.venue-assets.publication.publish');
            Route::delete('/{venue_public_id}/assets/{asset_public_id}/publication', [OwnerVenueAssetController::class, 'withdraw'])
                ->middleware(['permission:venue.manage,tenant', 'idempotency'])
                ->name('api.v1.tenant.venue-assets.publication.withdraw');
        });
    });

Route::prefix('platform')
    ->middleware(['auth:sanctum', 'throttle:platform', 'bindings'])
    ->group(function (): void {
        Route::prefix('marketplace')->group(function (): void {
            Route::get('/rentals', [PlatformMarketplaceController::class, 'listRentals'])
                ->middleware('permission:platform.marketplace.view,platform')
                ->name('api.v1.platform.marketplace.rentals.index');
            Route::get('/statements', [PlatformMarketplaceController::class, 'listStatements'])
                ->middleware('permission:platform.marketplace.view,platform')
                ->name('api.v1.platform.marketplace.statements.index');
            Route::post('/statements/{statement_public_id}/revisions', [PlatformMarketplaceController::class, 'reviseStatement'])
                ->middleware(['permission:platform.marketplace.disputes.manage,platform', 'idempotency'])
                ->name('api.v1.platform.marketplace.statements.revisions.store');
            Route::get('/disputes', [PlatformMarketplaceController::class, 'listDisputes'])
                ->middleware('permission:platform.marketplace.disputes.manage,platform')
                ->name('api.v1.platform.marketplace.disputes.index');
            Route::get('/disputes/{dispute_public_id}', [PlatformMarketplaceController::class, 'showDispute'])
                ->middleware('permission:platform.marketplace.disputes.manage,platform')
                ->name('api.v1.platform.marketplace.disputes.show');
            Route::post('/disputes/{dispute_public_id}/review', [PlatformMarketplaceController::class, 'startReview'])
                ->middleware(['permission:platform.marketplace.disputes.manage,platform', 'idempotency'])
                ->name('api.v1.platform.marketplace.disputes.review');
            Route::post('/disputes/{dispute_public_id}/notes', [PlatformMarketplaceController::class, 'addNote'])
                ->middleware(['permission:platform.marketplace.disputes.manage,platform', 'idempotency'])
                ->name('api.v1.platform.marketplace.disputes.notes.store');
            Route::post('/disputes/{dispute_public_id}/resolution', [PlatformMarketplaceController::class, 'resolve'])
                ->middleware(['permission:platform.marketplace.disputes.manage,platform', 'idempotency'])
                ->name('api.v1.platform.marketplace.disputes.resolution.store');
        });
    });
