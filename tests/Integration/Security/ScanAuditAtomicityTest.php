<?php

namespace Tests\Integration\Security;

use App\Models\User;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Scanning\Application\Actions\SubmitScanAction;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSummary;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
final class ScanAuditAtomicityTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_forced_audit_failure_leaves_no_scan_or_check_in_mutations(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        app(TenantContextStore::class)->bind(
            $scan['fixture']['tenant'],
            $scan['membership'],
            $scan['scanner'],
        );

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
                if (str_starts_with($action, 'scan.')) {
                    throw new \RuntimeException('Synthetic scan audit failure.');
                }

                return new AuditLog;
            }
        });

        try {
            app(SubmitScanAction::class)->execute(new ScanContext(
                tenantId: $scan['fixture']['tenant']->id,
                eventId: $scan['fixture']['event']->id,
                scannerId: $scan['scanner']->id,
                scannerType: 'staff_phone',
                qrPayload: $scan['token'],
            ));
            self::fail('Expected audit failure to abort the scan transaction.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Synthetic scan audit failure.', $exception->getMessage());
        }

        self::assertSame(0, DB::table('scan_events')->where('tenant_id', $scan['fixture']['tenant']->id)->count());
        self::assertSame(
            'not_checked_in',
            Attendee::query()->findOrFail($scan['credential']->attendee_id)->checkin_status,
        );
        self::assertNull(EventCheckInSummary::query()
            ->where('tenant_id', $scan['fixture']['tenant']->id)
            ->where('event_id', $scan['fixture']['event']->id)
            ->first());
    }
}
