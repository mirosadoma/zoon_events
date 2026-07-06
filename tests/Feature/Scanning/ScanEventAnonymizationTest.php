<?php

namespace Tests\Feature\Scanning;

use App\Modules\Attendees\Application\Jobs\AnonymizeEligibleAttendees;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Scanning\Application\Actions\SubmitScanAction;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
final class ScanEventAnonymizationTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_retention_redacts_scan_event_identity_fields_but_preserves_results(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        app(TenantContextStore::class)->bind(
            $scan['fixture']['tenant'],
            $scan['membership'],
            $scan['scanner'],
        );

        $submission = app(SubmitScanAction::class)->execute(new ScanContext(
            tenantId: $scan['fixture']['tenant']->id,
            eventId: $scan['fixture']['event']->id,
            scannerId: $scan['scanner']->id,
            scannerType: 'staff_phone',
            qrPayload: $scan['token'],
        ));

        $scanEvent = ScanEvent::query()->findOrFail($submission->scanEventId);
        self::assertSame('accepted', $scanEvent->result);
        self::assertSame('entry_granted', $scanEvent->reason);
        self::assertNotNull($scanEvent->attendee_display_name_ciphertext);
        self::assertNotSame('anonymized', $scanEvent->attendee_display_name_ciphertext);
        self::assertSame($scan['credential']->attendee_id, $scanEvent->attendee_id);
        self::assertSame(1, DB::table('audit_logs')->where('action', 'scan.accepted')->count());

        Attendee::query()
            ->where('tenant_id', $scan['fixture']['tenant']->id)
            ->whereKey($scan['credential']->attendee_id)
            ->update(['registered_at' => now()->subYears(2)]);

        $result = app(AnonymizeEligibleAttendees::class)->handle(
            $scan['fixture']['tenant']->id,
            Carbon::now()->subYear(),
            false,
        );

        self::assertSame(1, $result['anonymized']);
        $scanEvent->refresh();
        self::assertSame('accepted', $scanEvent->result);
        self::assertSame('entry_granted', $scanEvent->reason);
        self::assertSame($scan['credential']->id, $scanEvent->credential_id);
        self::assertNull($scanEvent->attendee_id);
        self::assertSame('anonymized', $scanEvent->attendee_display_name_ciphertext);
        self::assertSame(1, DB::table('audit_logs')->where('action', 'scan.accepted')->count());
        self::assertSame('anonymized', Attendee::query()->findOrFail($scan['credential']->attendee_id)->registration_status);
    }
}
