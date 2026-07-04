<?php

namespace App\Modules\Credentials\Providers;

use App\Modules\Credentials\Application\CredentialIssuerService;
use App\Modules\Credentials\Application\Signing\CanonicalCredentialToken;
use App\Modules\Credentials\Application\Signing\CredentialKeyRing;
use App\Modules\Credentials\Application\Signing\EnvironmentSecretReferenceLoader;
use App\Modules\Credentials\Contracts\CredentialIssuer;
use App\Modules\Credentials\Contracts\SecretReferenceLoader;
use Illuminate\Support\ServiceProvider;

final class CredentialsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SecretReferenceLoader::class, EnvironmentSecretReferenceLoader::class);
        $this->app->singleton(CredentialKeyRing::class, fn ($app) => new CredentialKeyRing(
            (string) config('credentials.current_key_id'),
            (array) config('credentials.key_ring'),
            $app->make(SecretReferenceLoader::class),
        ));
        $this->app->singleton(CanonicalCredentialToken::class);
        $this->app->bind(CredentialIssuer::class, CredentialIssuerService::class);
    }
}
