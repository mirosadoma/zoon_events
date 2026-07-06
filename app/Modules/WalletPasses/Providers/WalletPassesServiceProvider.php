<?php

namespace App\Modules\WalletPasses\Providers;

use App\Modules\Audit\Application\Listeners\Phase2\WalletPassAuditListener;
use App\Modules\Credentials\Domain\Events\CredentialLifecycleChanged;
use App\Modules\Events\Domain\Events\EventUpdated;
use App\Modules\WalletPasses\Application\Listeners\CredentialReissuedWalletSyncListener;
use App\Modules\WalletPasses\Application\Listeners\CredentialRevokedWalletSyncListener;
use App\Modules\WalletPasses\Application\Listeners\EventChangedWalletSyncListener;
use App\Modules\WalletPasses\Contracts\WalletAdapter;
use App\Modules\WalletPasses\Contracts\WalletPassPersonalDataAnonymizer;
use App\Modules\WalletPasses\Contracts\WalletSecretLoader;
use App\Modules\WalletPasses\Domain\Events\WalletPassRevocationFailed;
use App\Modules\WalletPasses\Domain\Events\WalletPassRevoked;
use App\Modules\WalletPasses\Domain\Events\WalletPassUpdated;
use App\Modules\WalletPasses\Domain\Events\WalletPassUpdateFailed;
use App\Modules\WalletPasses\Infrastructure\Adapters\Apple\ApplePassBuilder;
use App\Modules\WalletPasses\Infrastructure\Adapters\Apple\AppleWalletAdapter;
use App\Modules\WalletPasses\Infrastructure\Adapters\Google\GoogleWalletAdapter;
use App\Modules\WalletPasses\Infrastructure\Adapters\Google\GoogleWalletObjectBuilder;
use App\Modules\WalletPasses\Infrastructure\Persistence\DatabaseWalletPassPersonalDataAnonymizer;
use App\Modules\WalletPasses\Infrastructure\Secrets\EnvironmentWalletSecretLoader;
use App\Modules\WalletPasses\Testing\FakeWalletAdapter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class WalletPassesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnvironmentWalletSecretLoader::class);
        $this->app->singleton(WalletSecretLoader::class, EnvironmentWalletSecretLoader::class);
        $this->app->singleton(ApplePassBuilder::class);
        $this->app->singleton(GoogleWalletObjectBuilder::class);
        $this->app->singleton(AppleWalletAdapter::class);
        $this->app->singleton(GoogleWalletAdapter::class);
        $this->app->singleton(FakeWalletAdapter::class);
        $this->app->bind(WalletPassPersonalDataAnonymizer::class, DatabaseWalletPassPersonalDataAnonymizer::class);
        $this->app->bind(WalletAdapter::class, function ($app): WalletAdapter {
            $appleBinding = config('wallet.default_apple_adapter', 'fake');

            return match ($appleBinding) {
                'apple' => $app->make(AppleWalletAdapter::class),
                'google' => $app->make(GoogleWalletAdapter::class),
                default => $app->make(FakeWalletAdapter::class),
            };
        });
    }

    public function boot(): void
    {
        Event::listen(EventUpdated::class, [EventChangedWalletSyncListener::class, 'handle']);
        Event::listen(CredentialLifecycleChanged::class, [CredentialRevokedWalletSyncListener::class, 'handle']);
        Event::listen(CredentialLifecycleChanged::class, [CredentialReissuedWalletSyncListener::class, 'handle']);

        $audit = WalletPassAuditListener::class;
        Event::listen(WalletPassUpdated::class, [$audit, 'handleUpdated']);
        Event::listen(WalletPassUpdateFailed::class, [$audit, 'handleUpdateFailed']);
        Event::listen(WalletPassRevoked::class, [$audit, 'handleRevoked']);
        Event::listen(WalletPassRevocationFailed::class, [$audit, 'handleRevocationFailed']);
    }
}
