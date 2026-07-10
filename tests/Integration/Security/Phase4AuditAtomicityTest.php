<?php

namespace Tests\Integration\Security;

use App\Models\User;
use App\Modules\AccessControl\Application\Actions\AuthorizeGateAction;
use App\Modules\AccessControl\Domain\ValueObjects\AcsIntegrationContext;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-authorization')]
final class Phase4AuditAtomicityTest extends Phase4MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_forced_audit_failure_during_allow_leaves_no_access_or_scan_events(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $this->bindFailingAuditWriter();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan, admissionLane: true);
        $ctx = new AcsIntegrationContext($acs['event']->tenant_id, $acs['event']->id, ['authorize']);

        try {
            app(AuthorizeGateAction::class)->execute(
                $ctx,
                $acs['lane']->external_acs_lane_id,
                $acs['token'],
                'entry',
            );
            self::fail('Expected audit failure to abort the authorization transaction.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Synthetic access audit failure.', $exception->getMessage());
        }

        self::assertSame(0, DB::table('access_events')->count());
        self::assertSame(0, DB::table('scan_events')->where('scanner_type', 'acs_gate')->count());
    }

    public function test_forced_audit_failure_during_deny_leaves_no_access_events(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $this->bindFailingAuditWriter();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $acs['rule']->delete();
        $ctx = new AcsIntegrationContext($acs['event']->tenant_id, $acs['event']->id, ['authorize']);

        try {
            app(AuthorizeGateAction::class)->execute(
                $ctx,
                $acs['lane']->external_acs_lane_id,
                $acs['token'],
                'entry',
            );
            self::fail('Expected audit failure to abort the authorization transaction.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Synthetic access audit failure.', $exception->getMessage());
        }

        self::assertSame(0, AccessEvent::query()->count());
        self::assertSame(0, ScanEvent::query()->where('scanner_type', 'acs_gate')->count());
    }

    private function bindFailingAuditWriter(): void
    {
        app()->instance(AuditWriter::class, new class implements AuditWriter
        {
            public function write(
                string $scope,
                ?string $tenantId,
                string $action,
                string $outcome,
                ?User $actor = null,
                ?string $reasonCode = null,
                ?string $targetType = null,
                ?string $targetId = null,
                array $metadata = [],
                ?array $changeSummary = null,
            ): AuditLog {
                if (str_starts_with($action, 'access.')) {
                    throw new \RuntimeException('Synthetic access audit failure.');
                }

                return new AuditLog;
            }
        });
    }
}
