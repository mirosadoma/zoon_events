<?php

namespace Tests\Unit\Events;

use App\Modules\Events\Domain\EventRegistrationProfile;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-1')]
final class EventRegistrationProfileTest extends TestCase
{
    public function test_event_level_paid_ticketing_is_disabled(): void
    {
        $event = new Event([
            'tier' => 'public',
            'registration_mode' => 'paid_ticketing',
        ]);

        self::assertFalse(EventRegistrationProfile::requiresTicketConfiguration($event));
        self::assertFalse(EventRegistrationProfile::requiresPriceTiers($event));
        self::assertFalse(EventRegistrationProfile::allowsPaidTicketing('public'));
        self::assertFalse(EventRegistrationProfile::allowsPaidTicketing('both'));
        self::assertTrue(EventRegistrationProfile::capabilities($event)['uses_system_registration_slot']);
    }

    public function test_registration_mode_always_normalizes_to_free(): void
    {
        self::assertSame(
            'free_registration',
            EventRegistrationProfile::normalizeRegistrationMode('public', 'paid_ticketing'),
        );
        self::assertSame(
            'free_registration',
            EventRegistrationProfile::normalizeRegistrationMode('both', 'paid_ticketing'),
        );
    }
}
