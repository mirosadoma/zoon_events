<?php

namespace App\Modules\Attendees\Providers;

use App\Modules\Attendees\Application\EncryptedAttendeeCreator;
use App\Modules\Attendees\Contracts\AttendeeCreator;
use Illuminate\Support\ServiceProvider;

final class AttendeesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AttendeeCreator::class, EncryptedAttendeeCreator::class);
    }
}
