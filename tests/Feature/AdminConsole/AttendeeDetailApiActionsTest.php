<?php

namespace Tests\Feature\AdminConsole;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('admin-console')]
final class AttendeeDetailApiActionsTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_attendee_detail_api_actions_return_problem_details_not_service_unavailable(): void
    {
        $scan = $this->createIssuedCredentialScanFixture([
            'checkin.scan.submit',
            'checkin.desk.perform',
            'badge.print',
            'credential.revoke',
        ]);
        $eventId = $scan['fixture']['event']->id;
        $credential = $scan['credential'];
        $attendeeId = $credential->attendee_id;

        BadgeTemplate::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'event_id' => $eventId,
            'name' => 'Default Badge Template',
            'status' => 'active',
            'layout' => ['attendee_name' => [], 'qr' => [], 'ticket_type' => []],
            'paper_size' => 'a6',
            'printer_type' => 'fake',
            'created_by_user_id' => $scan['scanner']->id,
        ]);

        $this->actingAsScanner($scan);
        $headers = $this->scanHeaders($scan, (string) Str::ulid());

        $this->postJson("/api/v1/tenant/events/{$eventId}/badge-print-jobs", [
            'attendee_id' => (string) $attendeeId,
            'credential_id' => (string) $credential->id,
        ], $headers)
            ->assertJsonMissing(['code' => 'service_unavailable']);

        $this->actingAsScanner($scan);
        $this->postJson("/api/v1/tenant/events/{$eventId}/scans", [
            'scanner_type' => 'manual_desk',
            'credential_id' => (string) $credential->id,
            'override' => false,
            'override_reason' => null,
        ], $this->scanHeaders($scan, (string) Str::ulid()))
            ->assertJsonMissing(['code' => 'service_unavailable']);

        $this->actingAsScanner($scan);
        $this->postJson("/api/v1/tenant/events/{$eventId}/credentials/{$credential->id}/revoke", [
            'reason' => 'Test revoke from feature test',
        ], $this->scanHeaders($scan, (string) Str::ulid()))
            ->assertJsonMissing(['code' => 'service_unavailable']);
    }
}
