<?php

namespace Tests\Integration\Security;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Scanning\Application\Actions\SubmitScanAction;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
#[Group('phase-2-isolation')]
final class ScanIsolationTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_cross_event_scan_is_indistinguishable_from_unknown_credential(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $otherEvent = Event::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'slug' => 'other-event-'.uniqid(),
            'name_en' => 'Other Event',
            'name_ar' => 'فعالية أخرى',
            'tier' => 'public',
            'status' => 'published',
            'timezone' => 'Africa/Cairo',
            'start_at' => '2027-02-10 12:00:00',
            'end_at' => '2027-02-10 18:00:00',
            'registration_opens_at' => '2026-01-01 00:00:00',
            'registration_closes_at' => '2027-02-10 11:00:00',
            'capacity' => 10,
            'created_by_user_id' => $scan['fixture']['actor']->id,
            'published_by_user_id' => $scan['fixture']['actor']->id,
            'published_at' => now(),
        ]);

        app(TenantContextStore::class)->bind(
            $scan['fixture']['tenant'],
            $scan['membership'],
            $scan['scanner'],
        );

        $foreign = app(SubmitScanAction::class)->execute(new ScanContext(
            tenantId: $scan['fixture']['tenant']->id,
            eventId: $otherEvent->id,
            scannerId: $scan['scanner']->id,
            scannerType: 'staff_phone',
            qrPayload: $scan['token'],
        ));
        $unknown = app(SubmitScanAction::class)->execute(new ScanContext(
            tenantId: $scan['fixture']['tenant']->id,
            eventId: $scan['fixture']['event']->id,
            scannerId: $scan['scanner']->id,
            scannerType: 'staff_phone',
            qrPayload: 'zt1.invalid-token',
        ));

        self::assertSame('rejected', $foreign->decision->result);
        self::assertSame('rejected', $unknown->decision->result);
        self::assertSame($foreign->decision->reasonCode, $unknown->decision->reasonCode, 'Cross-event scan must be indistinguishable from unknown credential');
        self::assertNull($foreign->attendeeDisplayName);
        self::assertNull($unknown->attendeeDisplayName);
    }
}
