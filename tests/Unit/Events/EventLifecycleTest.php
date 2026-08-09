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
        self::assertTrue(EventStatus::Cancelled->canTransitionTo(EventStatus::Published));
        self::assertFalse(EventStatus::Archived->canTransitionTo(EventStatus::Live));
    }

    public function test_tier_defaults_never_enable_later_phase_capabilities(): void
    {
        $defaults = new EventTierDefaults;
        foreach (EventTier::cases() as $tier) {
            $capabilities = $defaults->for($tier)['enabled_capabilities'];
            self::assertSame(['registration', 'credential'], $capabilities);
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
            'event_venues' => 1,
            'agenda_items' => 1,
            'active_form_version_id' => '01TESTFORMVERSION0000000000',
            'active_ticket_types' => 0,
            'branding_active' => true,
            'configured_email_templates' => 3,
            'tier' => 'public',
            'registration_mode' => 'free_registration',
            'configured_categories' => 1,
        ];

        self::assertTrue($readiness->isReady($valid));
        self::assertEqualsCanonicalizing(
            ['published_agenda', 'active_form_version_id', 'active_branding', 'email_templates'],
            $readiness->missing([...$valid, 'agenda_items' => 0, 'active_form_version_id' => '', 'branding_active' => false, 'configured_email_templates' => 0]),
        );
        self::assertContains('event_categories', $readiness->missing([...$valid, 'configured_categories' => 0]));
        self::assertNotContains('event_categories', $readiness->missing([...$valid, 'configured_categories' => 1]));
        self::assertContains('email_templates', $readiness->missing([...$valid, 'configured_email_templates' => 2]));
        self::assertNotContains('email_templates', $readiness->missing([...$valid, 'configured_email_templates' => 3]));
        self::assertNotContains('main_image', $readiness->missing($valid));
    }

    public function test_publication_readiness_does_not_require_tickets_for_free_private_events(): void
    {
        $readiness = new PublicationReadiness;
        $valid = [
            'name_en' => 'Private Seminar',
            'name_ar' => 'ندوة خاصة',
            'timezone' => 'Africa/Cairo',
            'event_venues' => 1,
            'agenda_items' => 1,
            'active_form_version_id' => '01TESTFORMVERSION0000000000',
            'active_ticket_types' => 0,
            'branding_active' => true,
            'configured_email_templates' => 3,
            'tier' => 'private',
            'registration_mode' => 'free_registration',
            'configured_categories' => 1,
        ];

        self::assertTrue($readiness->isReady($valid));
        self::assertNotContains('active_ticket_type', $readiness->missing($valid));
        self::assertNotContains('active_badge_template', $readiness->missing($valid));
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

    public function test_draft_must_be_configured_before_published_transition(): void
    {
        self::assertFalse(EventStatus::Draft->canTransitionTo(EventStatus::Published));
        self::assertTrue(EventStatus::Configured->canTransitionTo(EventStatus::Published));
    }
}
