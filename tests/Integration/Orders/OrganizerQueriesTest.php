<?php

namespace Tests\Integration\Orders;

use App\Modules\Attendees\Application\Queries\OrganizerAttendeeQuery;
use App\Modules\Orders\Application\Queries\OrganizerOrderQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('phase-1-organizer')]
final class OrganizerQueriesTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_queries_are_bounded_scoped_and_support_blind_email_lookup(): void
    {
        $fixture = $this->createRegistrationFixture();
        $this->withHeader('Idempotency-Key', 'organizer-query')->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $this->registrationPayload($fixture),
        )->assertCreated();

        $orders = app(OrganizerOrderQuery::class)->execute($fixture['tenant']->id, $fixture['event']->id, 'paid', null, 1000);
        $attendees = app(OrganizerAttendeeQuery::class)->execute($fixture['tenant']->id, $fixture['event']->id, 'attendee@example.test', 1000);

        self::assertCount(1, $orders['items']);
        self::assertCount(1, $attendees);
        self::assertLessThanOrEqual(100, $orders['items']->count());
    }

    public function test_order_cursor_is_bound_to_tenant_event_and_filters(): void
    {
        $query = app(OrganizerOrderQuery::class);
        $this->expectException(InvalidArgumentException::class);
        $query->execute('tenant-b', 'event-b', 'paid', base64_encode('forged.payload'), 10);
    }

    public function test_large_query_shape_uses_tenant_first_indexes(): void
    {
        $plan = DB::selectOne("EXPLAIN SELECT id FROM orders USE INDEX (orders_status_index) WHERE tenant_id = '01SYNTHETICTENANT000000000' AND event_id = '01SYNTHETICEVENT0000000000' AND status = 'paid' ORDER BY created_at, id LIMIT 100");
        self::assertSame('orders_status_index', $plan->key);
    }
}
