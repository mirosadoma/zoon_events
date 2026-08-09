<?php

namespace App\Modules\EventSites\Providers;

use App\Modules\EventSites\Contracts\PublishedSiteReader;
use App\Modules\EventSites\Infrastructure\Persistence\DatabasePublishedSiteReader;
use Illuminate\Support\ServiceProvider;

final class EventSitesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PublishedSiteReader::class, DatabasePublishedSiteReader::class);
    }
}
