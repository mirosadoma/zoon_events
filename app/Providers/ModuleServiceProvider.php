<?php

namespace App\Providers;

use App\Modules\AccessControl\Providers\AccessControlServiceProvider;
use App\Modules\AdminConsole\Providers\AdminConsoleServiceProvider;
use App\Modules\Attendees\Providers\AttendeesServiceProvider;
use App\Modules\Audit\Providers\AuditServiceProvider;
use App\Modules\Authorization\Providers\AuthorizationServiceProvider;
use App\Modules\BadgePrinting\Providers\BadgePrintingServiceProvider;
use App\Modules\Credentials\Providers\CredentialsServiceProvider;
use App\Modules\Events\Providers\EventsServiceProvider;
use App\Modules\FeatureFlags\Providers\FeatureFlagsServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\IdentityVerification\Providers\IdentityVerificationServiceProvider;
use App\Modules\Integrations\Providers\IntegrationServiceProvider;
use App\Modules\Kiosk\Providers\KioskServiceProvider;
use App\Modules\Notifications\Providers\NotificationServiceProvider;
use App\Modules\Operations\Providers\OperationsServiceProvider;
use App\Modules\Orders\Providers\OrdersServiceProvider;
use App\Modules\Payments\Providers\PaymentsServiceProvider;
use App\Modules\Registration\Providers\RegistrationServiceProvider;
use App\Modules\Scanning\Providers\ScanningServiceProvider;
use App\Modules\Shared\Providers\SharedServiceProvider;
use App\Modules\Tenancy\Providers\TenancyServiceProvider;
use App\Modules\Ticketing\Providers\TicketingServiceProvider;
use App\Modules\WalletPasses\Providers\WalletPassesServiceProvider;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * @var list<class-string<ServiceProvider>>
     */
    private array $moduleProviders = [
        SharedServiceProvider::class,
        IdentityServiceProvider::class,
        TenancyServiceProvider::class,
        AuthorizationServiceProvider::class,
        AuditServiceProvider::class,
        FeatureFlagsServiceProvider::class,
        OperationsServiceProvider::class,
        IntegrationServiceProvider::class,
        AdminConsoleServiceProvider::class,
        EventsServiceProvider::class,
        RegistrationServiceProvider::class,
        TicketingServiceProvider::class,
        OrdersServiceProvider::class,
        PaymentsServiceProvider::class,
        AttendeesServiceProvider::class,
        CredentialsServiceProvider::class,
        IdentityVerificationServiceProvider::class,
        NotificationServiceProvider::class,
        WalletPassesServiceProvider::class,
        ScanningServiceProvider::class,
        KioskServiceProvider::class,
        BadgePrintingServiceProvider::class,
        AccessControlServiceProvider::class,
    ];

    public function register(): void
    {
        foreach ($this->moduleProviders as $provider) {
            $this->app->register($provider);
        }
    }
}
