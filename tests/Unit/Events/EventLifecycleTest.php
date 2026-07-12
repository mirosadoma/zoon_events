<?php

namespace Tests\Unit\Events;

use App\Exceptions\FoundationException;
use App\Modules\Events\Application\Publication\PublicationReadiness;
use App\Modules\Events\Domain\EventStatus;
use App\Modules\Events\Domain\EventTier;
use App\Modules\Events\Domain\EventTierDefaults;
use App\Modules\Shared\Http\Problems\ProblemFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-1')]
final class EventLifecycleTest extends TestCase
{
    public function test_lifecycle_allows_only_explicit_forward_transitions(): void
    {
        self::assertTrue(EventStatus::Draft->canTransitionTo(EventStatus::Configured));
        self::assertTrue(EventStatus::RegistrationOpen->canTransitionTo(EventStatus::Cancelled));
        self::assertFalse(EventStatus::Published->canTransitionTo(EventStatus::Draft));
        self::assertFalse(EventStatus::Cancelled->canTransitionTo(EventStatus::Published));
        self::assertFalse(EventStatus::Archived->canTransitionTo(EventStatus::Live));
    }

    public function test_tier_defaults_never_enable_later_phase_capabilities(): void
    {
        $defaults = new EventTierDefaults;
        foreach (EventTier::cases() as $tier) {
            $capabilities = $defaults->for($tier)['enabled_capabilities'];
            self::assertSame(['registration', 'ticketing', 'credential'], $capabilities);
            self::assertNotContains('wallet', $capabilities);
            self::assertNotContains('scanner', $capabilities);
        }
    }

    public function test_publication_readiness_lists_every_missing_requirement(): void
    {
        $readiness = new PublicationReadiness;
        $valid = [
            'name_en' => 'Synthetic Event',
            'name_ar' => 'فعالية تجريبية',
            'timezone' => 'Africa/Cairo',
            'start_at' => '2027-01-10T12:00:00Z',
            'end_at' => '2027-01-10T18:00:00Z',
            'registration_opens_at' => '2027-01-01T00:00:00Z',
            'registration_closes_at' => '2027-01-10T11:00:00Z',
            'active_form_version_id' => '01TESTFORMVERSION0000000000',
            'active_ticket_types' => 1,
            'branding_active' => true,
        ];

        self::assertTrue($readiness->isReady($valid));
        self::assertEqualsCanonicalizing(
            ['active_form_version_id', 'active_ticket_type', 'active_branding'],
            $readiness->missing([...$valid, 'active_form_version_id' => '', 'active_ticket_types' => 0, 'branding_active' => false]),
        );
    }

    public function test_event_not_publishable_problem_includes_missing_requirements(): void
    {
        $exception = new FoundationException(
            'event_not_publishable',
            409,
            'Conflict',
            'The event is not ready to publish.',
            ['missing' => ['active_ticket_type', 'active_branding']],
        );

        $problem = ProblemFactory::fromThrowable(
            $exception,
            '/api/v1/tenant/events/5/publish',
            'test-correlation-id',
        );

        self::assertSame(['active_ticket_type', 'active_branding'], $problem->missing);
    }
}
