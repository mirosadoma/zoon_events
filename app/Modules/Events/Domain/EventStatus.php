<?php

namespace App\Modules\Events\Domain;

enum EventStatus: string
{
    case Draft = 'draft';
    case Configured = 'configured';
    case Published = 'published';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case Live = 'live';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Draft => [self::Configured, self::Cancelled],
            self::Configured => [self::Published, self::Cancelled],
            self::Published => [self::RegistrationOpen, self::RegistrationClosed, self::Live, self::Cancelled, self::Configured],
            self::RegistrationOpen => [self::RegistrationClosed, self::Live, self::Cancelled, self::Configured],
            self::RegistrationClosed => [self::RegistrationOpen, self::Live, self::Cancelled, self::Configured],
            self::Live => [self::Completed, self::Cancelled],
            self::Completed => [self::Archived],
            self::Cancelled => [self::Archived],
            self::Archived => [],
        }, true);
    }
}
