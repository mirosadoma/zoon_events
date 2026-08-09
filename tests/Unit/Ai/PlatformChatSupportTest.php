<?php

namespace Tests\Unit\Ai;

use App\Modules\Ai\Application\Analytics\PlatformAnalyticsTools;
use App\Modules\Ai\Application\Chat\PlatformRagContextBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

final class PlatformChatSupportTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    #[Test]
    public function analytics_tools_return_structured_counts(): void
    {
        $fixture = $this->createRegistrationFixture();
        $tenantId = (int) $fixture['tenant']->id;

        $tools = new PlatformAnalyticsTools($tenantId);

        $events = $tools->getEventsCount();
        self::assertSame('analytics', $events['type']);
        self::assertGreaterThanOrEqual(1, $events['value']);

        $top = $tools->getTopEvent();
        self::assertSame('analytics', $top['type']);
        self::assertArrayHasKey('event_name', $top);

        $tickets = $tools->getTicketsSold('all_time');
        self::assertSame('analytics', $tickets['type']);
        self::assertArrayHasKey('value', $tickets);
    }

    #[Test]
    public function rag_context_builder_includes_event_data(): void
    {
        $fixture = $this->createRegistrationFixture();
        $tenantId = (int) $fixture['tenant']->id;

        $builder = new PlatformRagContextBuilder;
        $context = $builder->build($tenantId, 'en', 5);

        self::assertStringContainsString('Event:', $context);
        self::assertStringContainsString($fixture['event']->name_en, $context);
    }

    #[Test]
    public function tool_definitions_include_required_functions(): void
    {
        $tools = new PlatformAnalyticsTools(1);
        $definitions = $tools->toolDefinitions();

        $names = array_map(
            fn (array $def) => $def['function']['name'],
            $definitions,
        );

        self::assertContains('get_attendees_count', $names);
        self::assertContains('get_events_count', $names);
        self::assertContains('get_top_event', $names);
        self::assertContains('get_tickets_sold', $names);
    }
}
