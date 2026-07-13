<?php

namespace Tests\Unit\Events;

use App\Modules\Events\Domain\EventRegistrationProfile;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-1')]
final class EventRegistrationProfileTest extends TestCase
{
    public function test_paid_ticketing_only_for_public_events(): void
    {
        $event = new Event([
            'tier' => 'public',
            'registration_mode' => 'paid_ticketing',
        ]);

        self::assertTrue(EventRegistrationProfile::requiresTicketConfiguration($event));
        self::assertTrue(EventRegistrationProfile::requiresPriceTiers($event));
    }

    public function test_corporate_free_events_skip_ticket_configuration(): void
    {
        $event = new Event([
            'tier' => 'corporate',
            'registration_mode' => 'free_registration',
        ]);

        self::assertFalse(EventRegistrationProfile::requiresTicketConfiguration($event));
        self::assertTrue(EventRegistrationProfile::capabilities($event)['uses_system_registration_slot']);
    }

    public function test_non_public_tiers_cannot_keep_paid_ticketing_mode(): void
    {
        self::assertSame(
            'free_registration',
            EventRegistrationProfile::normalizeRegistrationMode('vip', 'paid_ticketing'),
        );
    }
}
