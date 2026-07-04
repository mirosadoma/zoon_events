<?php

namespace Tests\Performance;

use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentWebhookReceipt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('phase-1-performance')]
#[Group('payments')]
final class Phase1PaymentPerformanceTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;

    public function test_duplicate_callback_burst_converges_to_one_receipt_key(): void
    {
        $fixture = $this->createRegistrationFixture();
        $account = PaymentAccount::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'adapter_key' => 'fake',
            'secret_reference' => 'TEST_PAYMENT_SECRET',
            'account_reference' => 'synthetic',
            'webhook_route_token_hash' => hash('sha256', 'synthetic-route'),
            'mode' => 'test',
            'currency' => 'SAR',
            'status' => 'active',
        ]);
        $workers = [];
        for ($worker = 0; $worker < 16; $worker++) {
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/Phase1ContentionWorker.php'),
                'callback',
                '625',
                $account->id,
            ], base_path(), timeout: 180);
            $process->start();
            $workers[] = $process;
        }
        foreach ($workers as $process) {
            $process->wait();
            self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }
        self::assertSame(1, PaymentWebhookReceipt::query()
            ->where('payment_account_id', $account->id)
            ->where('provider_event_id', 'burst-event')
            ->count());
    }

    public function test_reconciliation_query_uses_due_attempt_index(): void
    {
        $plan = DB::selectOne("EXPLAIN SELECT id FROM payment_attempts FORCE INDEX (payment_attempts_reconcile_index) WHERE status IN ('pending','unknown') AND next_reconcile_at <= NOW() ORDER BY next_reconcile_at LIMIT 200");

        self::assertSame('payment_attempts_reconcile_index', $plan->key);
    }
}
