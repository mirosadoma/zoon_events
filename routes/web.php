<?php

use App\Modules\AdminConsole\Http\Controllers\Auth\ForgotPasswordController;
use App\Modules\AdminConsole\Http\Controllers\Auth\SessionController;
use App\Modules\AdminConsole\Http\Controllers\Visitor\VisitorPortalController;
use App\Modules\AdminConsole\Http\Controllers\DashboardController;
use App\Modules\AdminConsole\Http\Controllers\GeographyAdminController;
use App\Modules\AdminConsole\Http\Controllers\Kiosk\KioskModeController;
use App\Modules\AdminConsole\Http\Controllers\LandingController;
use App\Modules\AdminConsole\Http\Controllers\LocaleController;
use App\Modules\AdminConsole\Http\Controllers\MaintenancePageController;
use App\Modules\AdminConsole\Http\Controllers\MarketingSiteController;
use App\Modules\AdminConsole\Http\Controllers\Platform\PlatformMarketplacePageController;
use App\Modules\AdminConsole\Http\Controllers\PlatformPageController;
use App\Modules\AdminConsole\Http\Controllers\PlatformSubscriptionsController;
use App\Modules\AdminConsole\Http\Controllers\Public\PublicEventAgendaController;
use App\Modules\AdminConsole\Http\Controllers\Public\PublicEventRegistrationController;
use App\Modules\AdminConsole\Http\Controllers\Public\PublicSubscribeController;
use App\Modules\AdminConsole\Http\Controllers\SearchController;
use App\Modules\AdminConsole\Http\Controllers\SiteSettingsController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\Acs\AcsPageController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\Admin\AdminPageController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\Badges\BadgePageController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CategoryPageController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\DashboardController as CheckInDashboardController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\ScanEventsController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\ScannerController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\WalletPassesController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\Events\EventDashboardController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\Events\EventOperationsController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\Kiosk\EventKioskController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\ManualDesk\ManualDeskController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\PrivilegePageController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\Reports\EventReportController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\TenantMarketplacePageController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\TenantStatementPageController;
use App\Modules\AdminConsole\Http\Controllers\Tenant\TenantVenuePageController;
use App\Modules\IdentityVerification\Http\Controllers\Public\IdentityVerifyPageController;
use App\Modules\Notifications\Http\Controllers\InAppNotificationController;
use App\Modules\Notifications\Http\Controllers\Public\UnsubscribePageController;
use App\Modules\Operations\Http\Controllers\ApiDocsController;
use App\Modules\Orders\Http\Controllers\Public\PublicOrderPageController;
use App\Modules\Orders\Http\Controllers\Public\PublicOrderSignedWalletController;
use App\Modules\Shared\Support\LocaleDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Request $request) => redirect('/'.LocaleDetector::detect($request)));

Route::get('/identity/{event_slug}/{order_token}', [IdentityVerifyPageController::class, 'show'])
    ->middleware('throttle:public-event')
    ->name('public.identity.verify');

Route::middleware('kiosk.session.clear')->group(function (): void {
    Route::get('/kiosk/{device_code}', [KioskModeController::class, 'show'])->name('kiosk.mode');
    Route::get('/kiosk/{device_code}/{step}', [KioskModeController::class, 'show'])
        ->where('step', 'unlock|confirm|scan|lookup|result')
        ->name('kiosk.mode.step');
});

Route::prefix('{locale}')
    ->where(['locale' => 'en|ar'])
    ->group(function (): void {
        Route::get('/', LandingController::class)->name('home');
        Route::get('/about', [MarketingSiteController::class, 'about'])->name('marketing.about');
        Route::get('/solutions', [MarketingSiteController::class, 'solutions'])->name('marketing.solutions');
        Route::get('/contact', [MarketingSiteController::class, 'contact'])->name('marketing.contact');
        Route::get('/privacy', [MarketingSiteController::class, 'privacy'])->name('marketing.privacy');
        Route::get('/terms', [MarketingSiteController::class, 'terms'])->name('marketing.terms');
        Route::get('/maintenance', MaintenancePageController::class)->name('maintenance');
        Route::middleware('kiosk.session.clear')->group(function (): void {
            Route::get('/kiosk/{device_code}', [KioskModeController::class, 'show'])->name('kiosk.mode.localized');
            Route::get('/kiosk/{device_code}/{step}', [KioskModeController::class, 'show'])
                ->where('step', 'unlock|confirm|scan|lookup|result')
                ->name('kiosk.mode.step.localized');
        });
        Route::get('/public/orders/{public_reference}', [PublicOrderPageController::class, 'show'])
            ->name('public.order.show');
        Route::get('/public/orders/{public_reference}/wallet-passes/apple', [PublicOrderSignedWalletController::class, 'apple'])
            ->middleware('signed')
            ->name('public.order.wallet.apple');
        Route::get('/public/orders/{public_reference}/wallet-passes/google', [PublicOrderSignedWalletController::class, 'google'])
            ->middleware('signed')
            ->name('public.order.wallet.google');
        Route::get('/events/{event_slug}/agenda', [PublicEventAgendaController::class, 'show'])
            ->where('event_slug', '[a-z0-9-]+')
            ->middleware('throttle:public-event')
            ->name('public.events.agenda');
        Route::get('/events/{event_slug}/register', [PublicEventRegistrationController::class, 'show'])
            ->where('event_slug', '[a-z0-9-]+')
            ->middleware('throttle:public-event')
            ->name('public.events.register');
        Route::post('/events/{event_slug}/register', [PublicEventRegistrationController::class, 'store'])
            ->where('event_slug', '[a-z0-9-]+')
            ->middleware('throttle:public-event')
            ->name('public.events.register.store');
        Route::get('/events/{event_slug}/register/otp/{token}', [PublicEventRegistrationController::class, 'showOtp'])
            ->where('event_slug', '[a-z0-9-]+')
            ->where('token', '[A-Za-z0-9]+')
            ->middleware('throttle:public-event')
            ->name('public.events.register.otp');
        Route::post('/events/{event_slug}/register/otp/{token}', [PublicEventRegistrationController::class, 'verifyOtp'])
            ->where('event_slug', '[a-z0-9-]+')
            ->where('token', '[A-Za-z0-9]+')
            ->middleware('throttle:public-event')
            ->name('public.events.register.otp.verify');
        Route::get('/events/{event_slug}/register/payment/{public_reference}', [PublicEventRegistrationController::class, 'showPayment'])
            ->where('event_slug', '[a-z0-9-]+')
            ->middleware('throttle:public-event')
            ->name('public.events.register.payment');
        Route::post('/events/{event_slug}/register/payment/{public_reference}', [PublicEventRegistrationController::class, 'processPayment'])
            ->where('event_slug', '[a-z0-9-]+')
            ->middleware('throttle:public-event')
            ->name('public.events.register.payment.store');
        Route::get('/events/{event_slug}/register/payment-failed', [PublicEventRegistrationController::class, 'showPaymentFailed'])
            ->where('event_slug', '[a-z0-9-]+')
            ->middleware('throttle:public-event')
            ->name('public.events.register.payment-failed');
        Route::get('/events/{event_slug}/register/confirmation/{public_reference}', [PublicEventRegistrationController::class, 'showConfirmation'])
            ->where('event_slug', '[a-z0-9-]+')
            ->middleware('throttle:public-event')
            ->name('public.events.register.confirmation');
        Route::get('/notifications/unsubscribe', [UnsubscribePageController::class, 'show'])
            ->name('public.notifications.unsubscribe');
        Route::post('/notifications/unsubscribe', [UnsubscribePageController::class, 'store'])
            ->name('public.notifications.unsubscribe.confirm');
        Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
        Route::get('/subscribe/{plan}', [PublicSubscribeController::class, 'show'])->name('public.subscribe');

        Route::middleware('guest')->group(function (): void {
            Route::get('/login', [SessionController::class, 'create'])->name('login');
            Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:auth');
            Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
            Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:auth')->name('password.email');
            Route::get('/forgot-password/otp/{token}', [ForgotPasswordController::class, 'showOtp'])->name('password.otp');
            Route::post('/forgot-password/otp/{token}', [ForgotPasswordController::class, 'verifyOtp'])->middleware('throttle:auth')->name('password.otp.verify');
            Route::get('/forgot-password/reset/{resetToken}', [ForgotPasswordController::class, 'showReset'])->name('password.reset');
            Route::post('/forgot-password/reset/{resetToken}', [ForgotPasswordController::class, 'reset'])->middleware('throttle:auth')->name('password.update');
        });

        Route::middleware('auth')->group(function (): void {
            Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

            Route::middleware('visitor')->prefix('visitor')->group(function (): void {
                Route::get('/', [VisitorPortalController::class, 'index'])->name('visitor.events');
                Route::get('/events/{eventId}', [VisitorPortalController::class, 'showEvent'])->where('eventId', '[0-9]+')->name('visitor.events.show');
                Route::get('/profile', [VisitorPortalController::class, 'profile'])->name('visitor.profile');
                Route::patch('/profile', [VisitorPortalController::class, 'updateProfile'])->name('visitor.profile.update');
                Route::get('/password', [VisitorPortalController::class, 'passwordForm'])->name('visitor.password');
                Route::put('/password', [VisitorPortalController::class, 'updatePassword'])->name('visitor.password.update');
            });

            Route::middleware('not.visitor')->group(function (): void {
            Route::get('/dashboard', DashboardController::class)->name('dashboard');
            Route::get('/dashboard/search', SearchController::class)->name('dashboard.search');
            Route::get('/profile', [DashboardController::class, 'profile'])->name('profile');
            Route::patch('/profile', [DashboardController::class, 'updateProfile'])->name('profile.update');
            Route::get('/platform/site-settings', [SiteSettingsController::class, 'edit'])->name('platform.site-settings');
            Route::patch('/platform/site-settings', [SiteSettingsController::class, 'update'])->name('platform.site-settings.update');
            Route::get('/platform/geography', [GeographyAdminController::class, 'index'])->name('platform.geography');
            Route::post('/platform/geography/countries', [GeographyAdminController::class, 'storeCountry'])->name('platform.geography.countries.store');
            Route::patch('/platform/geography/countries/{country}', [GeographyAdminController::class, 'updateCountry'])->name('platform.geography.countries.update');
            Route::delete('/platform/geography/countries/{country}', [GeographyAdminController::class, 'destroyCountry'])->name('platform.geography.countries.destroy');
            Route::post('/platform/geography/cities', [GeographyAdminController::class, 'storeCity'])->name('platform.geography.cities.store');
            Route::patch('/platform/geography/cities/{city}', [GeographyAdminController::class, 'updateCity'])->name('platform.geography.cities.update');
            Route::delete('/platform/geography/cities/{city}', [GeographyAdminController::class, 'destroyCity'])->name('platform.geography.cities.destroy');
            Route::get('/platform/configuration', [PlatformPageController::class, 'configuration'])->name('platform.configuration');
            Route::get('/platform/marketplace', [PlatformMarketplacePageController::class, 'index'])->name('platform.marketplace.index');
            Route::get('/platform/marketplace/disputes/{dispute_public_id}', [PlatformMarketplacePageController::class, 'disputeShow'])->name('platform.marketplace.disputes.show');
            Route::get('/platform/subscriptions', [PlatformSubscriptionsController::class, 'index'])->name('platform.subscriptions.index');
            Route::get('/platform/subscriptions/create', [PlatformSubscriptionsController::class, 'create'])->name('platform.subscriptions.create');
            Route::get('/platform/subscriptions/{plan}/edit', [PlatformSubscriptionsController::class, 'edit'])->name('platform.subscriptions.edit');
            Route::get('/platform/subscriptions/{plan}', [PlatformSubscriptionsController::class, 'show'])->name('platform.subscriptions.show');
            Route::get('/platform/{section}', [PlatformPageController::class, 'show'])->name('dashboard.platform.section');
            Route::get('/notifications', [InAppNotificationController::class, 'index'])->name('notifications.index');
            Route::get('/api/notifications/unread-count', [InAppNotificationController::class, 'unreadCount'])->name('notifications.unread-count');
            Route::get('/api/notifications/recent', [InAppNotificationController::class, 'recent'])->name('notifications.recent');
            Route::patch('/api/notifications/{id}/read', [InAppNotificationController::class, 'markRead'])->name('notifications.mark-read');
            Route::patch('/api/notifications/read-all', [InAppNotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

            Route::get('/docs/api/openapi.yaml', ApiDocsController::class)->name('api.docs');

            Route::prefix('tenant/venues')->group(function (): void {
                Route::get('/', [TenantVenuePageController::class, 'index'])->name('tenant.venues.index');
                Route::get('/create', [TenantVenuePageController::class, 'create'])->name('tenant.venues.create');
                Route::get('/{venue_public_id}', [TenantVenuePageController::class, 'show'])->name('tenant.venues.show');
            });

            Route::prefix('tenant/marketplace')->group(function (): void {
                Route::get('/', [TenantMarketplacePageController::class, 'index'])->name('tenant.marketplace.index');
                Route::get('/rentals', [TenantMarketplacePageController::class, 'rentalsIndex'])->name('tenant.marketplace.rentals.index');
                Route::get('/rentals/{rental_public_id}', [TenantMarketplacePageController::class, 'rentalShow'])->name('tenant.marketplace.rentals.show');
                Route::get('/statements', [TenantStatementPageController::class, 'index'])->name('tenant.marketplace.statements.index');
                Route::get('/statements/{statement_public_id}', [TenantStatementPageController::class, 'show'])->name('tenant.marketplace.statements.show');
            });

            Route::prefix('admin')->group(function (): void {
                Route::get('/users', [AdminPageController::class, 'users'])->name('admin.users');
                Route::get('/roles', [AdminPageController::class, 'roles'])->name('admin.roles');
                Route::get('/tenant-settings', [AdminPageController::class, 'tenantSettings'])->name('admin.tenant-settings');
                Route::get('/audit-logs', [AdminPageController::class, 'auditLogs'])->name('admin.audit-logs');
            });

            Route::prefix('tenant/privileges')->group(function (): void {
                Route::get('/', [PrivilegePageController::class, 'index'])->name('tenant.privileges.index');
                Route::get('/create', [PrivilegePageController::class, 'create'])->name('tenant.privileges.create');
                Route::get('/{privilege_id}/edit', [PrivilegePageController::class, 'edit'])->where('privilege_id', '[0-9]+')->name('tenant.privileges.edit');
            });

            Route::prefix('tenant/categories')->group(function (): void {
                Route::get('/', [CategoryPageController::class, 'index'])->name('tenant.categories.index');
                Route::get('/create', [CategoryPageController::class, 'create'])->name('tenant.categories.create');
                Route::get('/{category_id}/edit', [CategoryPageController::class, 'edit'])->where('category_id', '[0-9]+')->name('tenant.categories.edit');
            });

            Route::prefix('tenant/events')->group(function (): void {
                Route::get('/', [EventDashboardController::class, 'index'])->name('tenant.events.index');
                Route::get('/create', [EventDashboardController::class, 'create'])->name('tenant.events.create');
                Route::get('/{event_id}', [EventDashboardController::class, 'show'])->where('event_id', '[0-9]+')->name('tenant.events.show');

                Route::get('/{event_id}/edit', [EventDashboardController::class, 'edit'])->where('event_id', '[0-9]+')->name('tenant.events.edit');
                Route::get('/{event_id}/registration-form', [EventDashboardController::class, 'registrationForm'])->name('tenant.registration.builder');
                Route::get('/{event_id}/agenda', [EventDashboardController::class, 'agenda'])->where('event_id', '[0-9]+')->name('tenant.events.agenda');
                Route::get('/{event_id}/categories', [EventDashboardController::class, 'categories'])->where('event_id', '[0-9]+')->name('tenant.events.categories');
                Route::get('/{event_id}/identity', [EventDashboardController::class, 'identityRequirements'])->name('tenant.identity.requirements');
                Route::get('/{event_id}/identity/review', [EventDashboardController::class, 'identityReview'])->name('tenant.identity.review');
                Route::get('/{event_id}/identity/verifications/{verification_id}', [EventDashboardController::class, 'identityVerificationDetail'])->name('tenant.identity.verification');
                Route::get('/{event_id}/registration-preview', [EventDashboardController::class, 'registrationPreview'])->name('tenant.registration.preview');
                Route::get('/{event_id}/agenda-preview', [EventDashboardController::class, 'agendaPreview'])->where('event_id', '[0-9]+')->name('tenant.agenda.preview');
                Route::get('/{event_id}/ticket-types', [EventDashboardController::class, 'ticketTypes'])->name('tenant.ticket-types.index');
                Route::get('/{event_id}/price-tiers', [EventDashboardController::class, 'priceTiers'])->name('tenant.price-tiers.index');
                Route::get('/{event_id}/orders', [EventOperationsController::class, 'orders'])->name('tenant.orders.index');
                Route::get('/{event_id}/orders/{order_id}', [EventOperationsController::class, 'orderShow'])->name('tenant.orders.show');
                Route::get('/{event_id}/attendees/export', [EventOperationsController::class, 'attendeesExport'])->name('tenant.attendees.export');
                Route::get('/{event_id}/attendees', [EventOperationsController::class, 'attendees'])->name('tenant.attendees.index');
                Route::get('/{event_id}/attendees/{attendee_id}', [EventOperationsController::class, 'attendeeShow'])->name('tenant.attendees.show');
                Route::get('/{event_id}/credentials', [EventOperationsController::class, 'credentials'])->name('tenant.credentials.index');
                Route::get('/{event_id}/credentials/{credential_id}', [EventOperationsController::class, 'credentialShow'])->name('tenant.credentials.show');
                Route::get('/{event_id}/wallet-passes', [WalletPassesController::class, 'index'])->name('tenant.wallet-passes.index');
                Route::get('/{event_id}/wallet-passes/{pass_id}', [WalletPassesController::class, 'show'])->name('tenant.wallet-passes.show');
                Route::get('/{event_id}/scanner', [ScannerController::class, 'show'])->name('tenant.checkin.scanner');
                Route::get('/{event_id}/check-in-dashboard', [CheckInDashboardController::class, 'show'])->name('tenant.checkin.dashboard');
                Route::get('/{event_id}/scan-events', [ScanEventsController::class, 'index'])->name('tenant.scan-events.index');
                Route::get('/{event_id}/kiosks', [EventKioskController::class, 'index'])->name('tenant.kiosks.index');
                Route::get('/{event_id}/kiosks/{kiosk_id}', [EventKioskController::class, 'show'])->name('tenant.kiosks.show');
                Route::get('/{event_id}/badge-templates', [BadgePageController::class, 'templates'])->name('tenant.badge-templates.index');
                Route::get('/{event_id}/badge-print-jobs', [BadgePageController::class, 'printJobs'])->name('tenant.badge-print-jobs.index');
                Route::get('/{event_id}/manual-desk', [ManualDeskController::class, 'index'])->name('tenant.manual-desk.index');
                Route::get('/{event_id}/manual-desk/walk-up', [ManualDeskController::class, 'walkUp'])->name('tenant.manual-desk.walk-up');
                Route::get('/{event_id}/acs', [AcsPageController::class, 'overview'])->name('tenant.acs.overview');
                Route::get('/{event_id}/acs/zones', [AcsPageController::class, 'zones'])->name('tenant.acs.zones');
                Route::get('/{event_id}/acs/lanes', [AcsPageController::class, 'lanes'])->name('tenant.acs.lanes');
                Route::get('/{event_id}/acs/rules', [AcsPageController::class, 'rules'])->name('tenant.acs.rules');
                Route::get('/{event_id}/acs/access-logs', [AcsPageController::class, 'accessLogs'])->name('tenant.acs.access-logs');
                Route::get('/{event_id}/acs/gate-health', [AcsPageController::class, 'gateHealth'])->name('tenant.acs.gate-health');
                Route::get('/{event_id}/reports', [EventReportController::class, 'show'])->name('tenant.reports.show');
            });
            });
        });
    });
