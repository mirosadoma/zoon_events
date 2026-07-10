<?php

namespace App\Modules\AccessControl\Providers;

use App\Modules\AccessControl\Contracts\AcsAdapter;
use App\Modules\AccessControl\Domain\Context\AcsIntegrationContextStore;
use App\Modules\AccessControl\Domain\Events\AccessEventIngested;
use App\Modules\AccessControl\Domain\Events\AcsIntegrationCredentialRegistered;
use App\Modules\AccessControl\Domain\Events\AcsLaneCreated;
use App\Modules\AccessControl\Domain\Events\AcsRuleCreated;
use App\Modules\AccessControl\Domain\Events\AcsZoneCreated;
use App\Modules\AccessControl\Domain\Events\AcsZoneUpdated;
use App\Modules\AccessControl\Domain\Events\EmergencyCleared;
use App\Modules\AccessControl\Domain\Events\EmergencyRaised;
use App\Modules\AccessControl\Domain\Events\GateAuthorized;
use App\Modules\AccessControl\Domain\Events\GateDenied;
use App\Modules\AccessControl\Infrastructure\Adapters\MockAcsAdapter;
use App\Modules\AccessControl\Testing\FakeAcsAdapter;
use App\Modules\Audit\Application\Listeners\Phase4\AccessEventAuditListener;
use App\Modules\Audit\Application\Listeners\Phase4\AcsConfigAuditListener;
use App\Modules\Audit\Application\Listeners\Phase4\EmergencyAuditListener;
use App\Modules\Audit\Application\Listeners\Phase4\GateDecisionAuditListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class AccessControlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AcsIntegrationContextStore::class);
        $this->app->singleton(FakeAcsAdapter::class);

        $this->app->bind(AcsAdapter::class, function ($app): AcsAdapter {
            return match (config('acs.default_acs_adapter', 'mock')) {
                default => $app->make(MockAcsAdapter::class),
            };
        });
    }

    public function boot(): void
    {
        $gateListener = GateDecisionAuditListener::class;
        Event::listen(GateAuthorized::class, [$gateListener, 'handleAuthorized']);
        Event::listen(GateDenied::class, [$gateListener, 'handleDenied']);

        $configListener = AcsConfigAuditListener::class;
        Event::listen(AcsZoneCreated::class, [$configListener, 'handleZoneCreated']);
        Event::listen(AcsZoneUpdated::class, [$configListener, 'handleZoneUpdated']);
        Event::listen(AcsLaneCreated::class, [$configListener, 'handleLaneCreated']);
        Event::listen(AcsRuleCreated::class, [$configListener, 'handleRuleCreated']);
        Event::listen(AcsIntegrationCredentialRegistered::class, [$configListener, 'handleCredentialRegistered']);

        Event::listen(AccessEventIngested::class, [AccessEventAuditListener::class, 'handle']);

        $emergencyListener = EmergencyAuditListener::class;
        Event::listen(EmergencyRaised::class, [$emergencyListener, 'handleRaised']);
        Event::listen(EmergencyCleared::class, [$emergencyListener, 'handleCleared']);
    }
}
