<?php

namespace Tests\Performance;

use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('phase-1-performance')]
#[Group('ticket-inventory')]
final class Phase1TicketingPerformanceTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;

    public function test_ten_thousand_attempt_fixture_never_exceeds_allocation(): void
    {
        $capacity = 137;
        $fixture = $this->createRegistrationFixture(capacity: $capacity);
        $workers = [];
        for ($worker = 0; $worker < 16; $worker++) {
            $attempts = 625;
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/Phase1ContentionWorker.php'),
                'inventory',
                (string) $attempts,
                $fixture['tenant']->id,
                $fixture['event']->id,
                $fixture['ticket']->id,
            ], base_path(), timeout: 180);
            $process->start();
            $workers[] = $process;
        }
        $successes = 0;
        foreach ($workers as $process) {
            $process->wait();
            self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $successes += (int) trim($process->getOutput());
        }
        $inventory = TicketInventory::query()->where('ticket_type_id', $fixture['ticket']->id)->firstOrFail();
        self::assertSame($capacity, $successes);
        self::assertSame($capacity, $inventory->held_quantity);
        self::assertLessThanOrEqual($capacity, $inventory->held_quantity + $inventory->sold_quantity);
    }

    public function test_hold_expiry_query_uses_the_bounded_expiry_index(): void
    {
        $plan = DB::selectOne("EXPLAIN SELECT id, tenant_id FROM inventory_holds FORCE INDEX (inventory_holds_expiry_idx) WHERE status = 'active' AND expires_at <= NOW() ORDER BY id LIMIT 500");

        self::assertSame('inventory_holds_expiry_idx', $plan->key);
        self::assertLessThanOrEqual(500, (int) $plan->rows);
    }
}
