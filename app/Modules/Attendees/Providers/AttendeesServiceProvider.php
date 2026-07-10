<?php

namespace App\Modules\Attendees\Providers;

use App\Modules\Attendees\Application\EncryptedAttendeeCreator;
use App\Modules\Attendees\Contracts\AttendeeCreator;
use App\Modules\Attendees\Domain\Events\WalkUpAttendeeRegistered;
use App\Modules\Audit\Application\Listeners\Phase3\WalkUpAuditListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class AttendeesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AttendeeCreator::class, EncryptedAttendeeCreator::class);
    }

    public function boot(): void
    {
        Event::listen(WalkUpAttendeeRegistered::class, [WalkUpAuditListener::class, 'handleRegistered']);
    }
}
