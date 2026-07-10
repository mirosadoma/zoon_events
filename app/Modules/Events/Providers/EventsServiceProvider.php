<?php

namespace App\Modules\Events\Providers;

use App\Modules\Events\Application\Context\DatabasePublicEventContextResolver;
use App\Modules\Events\Application\Context\DatabasePublicOrderHostAuthorizer;
use App\Modules\Events\Contracts\ConfirmationEventReader;
use App\Modules\Events\Contracts\EventScope;
use App\Modules\Events\Contracts\PublicEventContextResolver;
use App\Modules\Events\Contracts\PublicOrderHostAuthorizer;
use App\Modules\Events\Domain\Context\PublicEventContextStore;
use App\Modules\Events\Infrastructure\Persistence\DatabaseConfirmationEventReader;
use App\Modules\Events\Infrastructure\Persistence\DatabaseEventScope;
use Illuminate\Support\ServiceProvider;

final class EventsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PublicEventContextStore::class);
        $this->app->bind(PublicEventContextResolver::class, DatabasePublicEventContextResolver::class);
        $this->app->bind(PublicOrderHostAuthorizer::class, DatabasePublicOrderHostAuthorizer::class);
        $this->app->bind(EventScope::class, DatabaseEventScope::class);
        $this->app->bind(ConfirmationEventReader::class, DatabaseConfirmationEventReader::class);
    }
}
