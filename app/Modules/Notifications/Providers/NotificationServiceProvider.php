<?php

namespace App\Modules\Notifications\Providers;

use App\Modules\Notifications\Application\ConfirmationIntentFactory;
use App\Modules\Notifications\Application\NotificationAdapterRegistry;
use App\Modules\Notifications\Contracts\ConfirmationIntentCreator;
use App\Modules\Notifications\Contracts\NotificationDestinationAnonymizer;
use App\Modules\Notifications\Infrastructure\Adapters\SmtpEmailAdapter;
use App\Modules\Notifications\Infrastructure\Adapters\UnifonicSmsAdapter;
use App\Modules\Notifications\Infrastructure\Persistence\DatabaseNotificationDestinationAnonymizer;
use App\Modules\Notifications\Testing\FakeEmailAdapter;
use App\Modules\Notifications\Testing\FakeSmsAdapter;
use Illuminate\Support\ServiceProvider;

final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ConfirmationIntentCreator::class, ConfirmationIntentFactory::class);
        $this->app->bind(NotificationDestinationAnonymizer::class, DatabaseNotificationDestinationAnonymizer::class);
        $this->app->singleton(SmtpEmailAdapter::class);
        $this->app->singleton(UnifonicSmsAdapter::class);
        $this->app->singleton(FakeEmailAdapter::class);
        $this->app->singleton(FakeSmsAdapter::class);
        $this->app->singleton(NotificationAdapterRegistry::class, fn ($app) => new NotificationAdapterRegistry([
            'smtp' => $app->make(SmtpEmailAdapter::class),
            'log' => $app->make(SmtpEmailAdapter::class),
            'fake' => $app->make(FakeEmailAdapter::class),
            'fake-email' => $app->make(FakeEmailAdapter::class),
            'unifonic' => $app->make(UnifonicSmsAdapter::class),
            'fake-sms' => $app->make(FakeSmsAdapter::class),
        ]));
    }
}
