<?php

namespace Tests\Performance;

use App\Modules\AccessControl\Application\Actions\AuthorizeGateAction;
use App\Modules\AccessControl\Domain\ValueObjects\AcsIntegrationContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('performance')]
final class GateAuthorizationLatencyTest extends Phase4MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_authorize_gate_action_p50_stays_within_configured_budget(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $ctx = new AcsIntegrationContext($acs['event']->tenant_id, $acs['event']->id, ['authorize']);
        $action = app(AuthorizeGateAction::class);
        $budgetMs = (int) config('acs.authorization.latency_budget_ms', 500);
        $durations = [];

        for ($i = 0; $i < 20; $i++) {
            $started = microtime(true);
            $action->execute(
                $ctx,
                $acs['lane']->external_acs_lane_id,
                $acs['token'],
                'entry',
            );
            $durations[] = (microtime(true) - $started) * 1000;
        }

        sort($durations);
        $p50 = $durations[(int) floor((count($durations) - 1) * 0.5)];

        self::assertLessThanOrEqual(
            $budgetMs,
            $p50,
            "Expected p50 authorize latency <= {$budgetMs}ms; observed {$p50}ms.",
        );
    }
}
