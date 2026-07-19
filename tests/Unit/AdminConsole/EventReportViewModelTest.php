<?php

namespace Tests\Unit\AdminConsole;

use App\Models\User;
use App\Modules\AdminConsole\ViewModels\Reports\EventReportViewModel;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('admin-dashboard')]
#[Group('phase-1')]
final class EventReportViewModelTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_report_payload_includes_summary_breakdowns_and_key_rates(): void
    {
        $fixture = $this->createRegistrationFixture();

        $this->registerAttendee($fixture, 'Ada', 'Checked', 'ada.checked@example.test', 'report-reg-1');
        $this->registerAttendee($fixture, 'Ben', 'Waiting', 'ben.waiting@example.test', 'report-reg-2');

        $attendees = Attendee::query()
            ->where('tenant_id', $fixture['tenant']->id)
            ->where('event_id', $fixture['event']->id)
            ->orderBy('id')
            ->get();

        self::assertCount(2, $attendees);

        $checkedIn = $attendees[0];
        $waiting = $attendees[1];

        $checkedIn->forceFill([
            'checkin_status' => 'checked_in',
            'first_checked_in_at' => now(),
        ])->save();

        Order::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $fixture['event']->id,
            'public_reference' => 'REP-'.Str::lower((string) Str::ulid()),
            'access_token_hash' => hash('sha256', 'report-pending-token-'.Str::ulid()),
            'status' => 'pending_payment',
            'buyer_name_ciphertext' => 'cipher-name',
            'buyer_email_ciphertext' => 'cipher-email',
            'buyer_email_index' => hash('sha256', 'pending.buyer@example.test'),
            'encryption_key_id' => 'test-key',
            'subtotal_minor' => 1500,
            'tax_minor' => 0,
            'fees_minor' => 0,
            'total_minor' => 1500,
            'currency' => 'EGP',
            'locale' => 'en',
        ]);

        ScanEvent::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $fixture['event']->id,
            'attendee_id' => $checkedIn->id,
            'scanner_type' => 'staff_phone',
            'scanner_id' => 'report-scanner',
            'direction' => 'in',
            'result' => 'accepted',
            'reason' => 'entry_granted',
            'offline_mode' => false,
            'scanned_at' => now()->subMinutes(10),
        ]);
        ScanEvent::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $fixture['event']->id,
            'attendee_id' => $checkedIn->id,
            'scanner_type' => 'staff_phone',
            'scanner_id' => 'report-scanner',
            'direction' => 'in',
            'result' => 'rejected',
            'reason' => 'duplicate_attempt',
            'offline_mode' => false,
            'scanned_at' => now()->subMinutes(5),
        ]);
        ScanEvent::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $fixture['event']->id,
            'attendee_id' => $waiting->id,
            'scanner_type' => 'staff_phone',
            'scanner_id' => 'report-scanner',
            'direction' => 'in',
            'result' => 'rejected',
            'reason' => 'invalid_qr',
            'offline_mode' => false,
            'scanned_at' => now()->subMinutes(2),
        ]);

        $payload = app(EventReportViewModel::class)->make(
            $fixture['event']->fresh(),
            (string) $fixture['tenant']->id,
        );

        $report = $payload['report'];
        $summary = $report['summary'];

        self::assertSame((string) $fixture['tenant']->id, $payload['tenantId']);
        self::assertSame($fixture['event']->id, $payload['event']['id']);

        self::assertTrue($summary['registrations']['available']);
        self::assertSame(2, $summary['registrations']['value']);
        self::assertSame(1, $summary['checked_in_attendees']['value']);
        self::assertSame(50.0, $summary['checkin_rate']['value']);

        self::assertSame(2, $summary['paid_orders']['value']);
        self::assertSame(66.7, $summary['payment_success_rate']['value']);
        self::assertTrue($summary['revenue_minor']['available']);
        self::assertIsString($summary['currency']);

        self::assertSame(1, $summary['accepted_scans']['value']);
        self::assertSame(2, $summary['rejected_scans']['value']);
        self::assertSame(33.3, $summary['checkin_success_rate']['value']);
        self::assertTrue($summary['first_scan_success_rate']['available']);
        self::assertSame(50.0, $summary['first_scan_success_rate']['value']);

        self::assertContains('status', array_keys($report['orders_by_status'][0] ?? []));
        self::assertContains('count', array_keys($report['orders_by_status'][0] ?? []));
        self::assertContains('revenue_minor', array_keys($report['orders_by_status'][0] ?? []));

        $statuses = collect($report['orders_by_status'])->pluck('status')->all();
        self::assertContains('paid', $statuses);
        self::assertContains('pending_payment', $statuses);

        self::assertIsArray($report['categories']);
        self::assertIsArray($report['ticket_types']);
        self::assertNotEmpty($report['checkins_by_day']);
        self::assertArrayHasKey('date', $report['checkins_by_day'][0]);
        self::assertArrayHasKey('accepted_scans', $report['checkins_by_day'][0]);
        self::assertArrayHasKey('unique_attendees', $report['checkins_by_day'][0]);

        self::assertSame(['queued', 'printed', 'failed'], array_keys($report['badge_jobs']['by_status']));
        self::assertArrayHasKey('reprints', $report['badge_jobs']);
        self::assertArrayHasKey('total', $report['kiosks']);
        self::assertArrayHasKey('online', $report['kiosks']);

        $rejectReasons = collect($report['top_reject_reasons'])->pluck('reason')->all();
        self::assertContains('invalid_qr', $rejectReasons);
        self::assertContains('duplicate_attempt', $rejectReasons);

        $ticketType = collect($report['ticket_types'])->firstWhere('id', (string) $fixture['ticket']->id);
        self::assertNotNull($ticketType);
        self::assertSame(2, $ticketType['attendees']);
        self::assertSame(1, $ticketType['checked_in']);
    }

    /** @param array{actor:User,tenant:Tenant,event:Event,form:RegistrationFormVersion,ticket:TicketType} $fixture */
    private function registerAttendee(array $fixture, string $firstName, string $lastName, string $email, string $idempotencyKey): void
    {
        $payload = $this->registrationPayload($fixture);
        $payload['buyer'] = ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email];
        $payload['attendee'] = ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email];
        $payload['answers'] = [
            'full_name' => "{$firstName} {$lastName}",
            'email' => $email,
            'phone' => '+966501234567',
        ];

        $this->withHeader('Idempotency-Key', $idempotencyKey)->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $payload,
        )->assertCreated();
    }
}
