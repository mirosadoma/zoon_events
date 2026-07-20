<?php

namespace Tests\Feature\Scanning;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\KioskSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
#[Group('kiosk')]
final class KioskBadgePrintTest extends Phase2MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_kiosk_cannot_print_badge_before_check_in(): void
    {
        ['secret' => $secret, 'scan' => $scan] = $this->prepareKiosk();

        $this->withHeaders($this->kioskHeaders($secret, 'print-before-checkin'))
            ->postJson('/api/v1/kiosk/v1/badge-print-jobs', [
                'attendee_id' => (string) $scan['credential']->attendee_id,
                'credential_id' => (string) $scan['credential']->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'badge_print_checkin_required');
    }

    public function test_kiosk_print_requires_attendee_and_credential_ids(): void
    {
        ['secret' => $secret] = $this->prepareKiosk();

        $this->withHeaders($this->kioskHeaders($secret, 'print-empty-body'))
            ->postJson('/api/v1/kiosk/v1/badge-print-jobs', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed');
    }

    public function test_kiosk_can_print_badge_after_successful_check_in(): void
    {
        ['secret' => $secret, 'scan' => $scan] = $this->prepareKiosk();

        $scanResponse = $this->withHeaders($this->kioskHeaders($secret, 'scan-then-print'))
            ->postJson('/api/v1/kiosk/v1/scans', [
                'qr_payload' => $scan['token'],
            ])
            ->assertOk()
            ->assertJsonPath('data.result', 'accepted');

        $attendeeId = $scanResponse->json('data.attendee_id');
        $credentialId = $scanResponse->json('data.credential_id');

        self::assertNotEmpty($attendeeId);
        self::assertNotEmpty($credentialId);

        $preview = $this->withHeaders($this->kioskHeaders($secret, 'preview-before-print'))
            ->postJson('/api/v1/kiosk/v1/badge-print-jobs/preview', [
                'attendee_id' => (string) $attendeeId,
                'credential_id' => (string) $credentialId,
                'field_overrides' => [
                    'job_title' => 'Speaker',
                ],
            ])
            ->assertOk();

        $previewHtml = $preview->json('data.print_html');
        self::assertIsString($previewHtml);
        self::assertStringContainsString('badge-print-sheet', $previewHtml);
        self::assertIsArray($preview->json('data.editable_fields'));

        $printResponse = $this->withHeaders($this->kioskHeaders($secret, 'print-after-checkin'))
            ->postJson('/api/v1/kiosk/v1/badge-print-jobs', [
                'attendee_id' => (string) $attendeeId,
                'credential_id' => (string) $credentialId,
                'field_overrides' => [
                    'job_title' => 'Speaker',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'printed');

        $printHtml = $printResponse->json('data.print_html');
        self::assertIsString($printHtml);
        self::assertStringContainsString('badge-print-sheet', $printHtml);
        self::assertStringContainsString('window.print()', $printHtml);
    }

    public function test_kiosk_can_print_badge_when_visitor_already_checked_in(): void
    {
        ['secret' => $secret, 'scan' => $scan] = $this->prepareKiosk();

        Attendee::query()
            ->whereKey($scan['credential']->attendee_id)
            ->update([
                'checkin_status' => 'checked_in',
                'first_checked_in_at' => now(),
            ]);

        $this->withHeaders($this->kioskHeaders($secret, 'print-already-checked-in'))
            ->postJson('/api/v1/kiosk/v1/badge-print-jobs', [
                'attendee_id' => (string) $scan['credential']->attendee_id,
                'credential_id' => (string) $scan['credential']->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'printed');
    }

    /**
     * @return array{secret: string, scan: array<string, mixed>}
     */
    private function prepareKiosk(): array
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $tenantId = $scan['fixture']['tenant']->id;
        $eventId = $scan['fixture']['event']->id;

        BadgeTemplate::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'name' => 'Kiosk Badge Template',
            'status' => 'active',
            'layout' => ['attendee_name' => [], 'qr' => [], 'ticket_type' => []],
            'paper_size' => 'a6',
            'printer_type' => 'fake',
            'created_by_user_id' => $scan['scanner']->id,
        ]);

        $kiosk = Kiosk::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'device_name' => 'Badge print kiosk',
            'device_code' => 'BADGE001',
            'status' => 'online',
            'printer_status' => 'ready',
            'confirmation_required' => false,
        ]);

        $secret = sodium_bin2base64(random_bytes(40), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        KioskSession::query()->create([
            'tenant_id' => $tenantId,
            'kiosk_id' => $kiosk->id,
            'secret_hash' => hash('sha256', $secret),
            'confirmed_at' => now(),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
        ]);

        return ['secret' => $secret, 'scan' => $scan];
    }

    /** @return array<string, string> */
    private function kioskHeaders(string $secret, string $idempotencyKey): array
    {
        return [
            'Authorization' => 'KioskSession '.$secret,
            'Idempotency-Key' => $idempotencyKey.'-'.Str::lower((string) Str::ulid()),
            'Accept' => 'application/json',
        ];
    }
}
