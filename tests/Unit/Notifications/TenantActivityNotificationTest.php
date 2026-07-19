<?php

namespace Tests\Unit\Notifications;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Audit\Application\Listeners\Phase2\ScanAuditListener;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Notifications\Infrastructure\Persistence\Models\InAppNotification;
use App\Modules\Scanning\Domain\Events\ScanAccepted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('notifications')]
final class TenantActivityNotificationTest extends Phase2MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_registration_completed_audit_notifies_registration_managers(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['registration.manage']);
        $tenantId = (string) $scan['fixture']['tenant']->id;
        $eventId = (string) $scan['fixture']['event']->id;
        $manager = $scan['scanner'];

        InAppNotification::query()->where('user_id', $manager->id)->delete();

        app(AuditWriter::class)->write(
            'tenant',
            $tenantId,
            'registration.free_completed',
            'succeeded',
            targetType: 'order',
            targetId: 'order-1',
            metadata: ['event_id' => $eventId, 'attendee_id' => '7'],
        );

        $notification = InAppNotification::query()
            ->where('user_id', $manager->id)
            ->where('action', 'registration.free_completed')
            ->latest('id')
            ->first();

        self::assertNotNull($notification);
        self::assertSame("/tenant/events/{$eventId}/attendees", $notification->link);
        self::assertSame($eventId, $notification->data['event_id'] ?? null);
    }

    public function test_badge_printed_audit_notifies_badge_printers(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['badge.print']);
        $tenantId = (string) $scan['fixture']['tenant']->id;
        $eventId = (string) $scan['fixture']['event']->id;
        $printer = $scan['scanner'];

        InAppNotification::query()->where('user_id', $printer->id)->delete();

        app(AuditWriter::class)->write(
            'tenant',
            $tenantId,
            'badge_print.printed',
            'succeeded',
            targetType: 'badge_print_job',
            targetId: 'job-1',
            metadata: ['event_id' => $eventId, 'attendee_id' => '7'],
        );

        $notification = InAppNotification::query()
            ->where('user_id', $printer->id)
            ->where('action', 'badge_print.printed')
            ->latest('id')
            ->first();

        self::assertNotNull($notification);
        self::assertSame("/tenant/events/{$eventId}/badge-print-jobs", $notification->link);
    }

    public function test_scan_accepted_without_tenant_context_notifies_checkin_viewers(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.dashboard.view']);
        $tenantId = (string) $scan['fixture']['tenant']->id;
        $eventId = (string) $scan['fixture']['event']->id;
        $viewer = $scan['scanner'];

        InAppNotification::query()->where('user_id', $viewer->id)->delete();
        AuditLog::query()->where('action', 'scan.accepted')->where('tenant_id', $tenantId)->delete();

        app(ScanAuditListener::class)->handleAccepted(new ScanAccepted(
            tenantId: $tenantId,
            eventId: $eventId,
            scanEventId: (string) Str::ulid(),
            credentialId: (string) $scan['credential']->id,
            reasonCode: 'accepted',
        ));

        self::assertTrue(
            AuditLog::query()
                ->where('tenant_id', $tenantId)
                ->where('action', 'scan.accepted')
                ->exists()
        );

        $notification = InAppNotification::query()
            ->where('user_id', $viewer->id)
            ->where('action', 'scan.accepted')
            ->latest('id')
            ->first();

        self::assertNotNull($notification);
        self::assertSame("/tenant/events/{$eventId}/check-in-dashboard", $notification->link);
    }
}
