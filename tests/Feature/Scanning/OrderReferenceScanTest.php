<?php

namespace Tests\Feature\Scanning;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
final class OrderReferenceScanTest extends Phase2MySqlTestCase
{
    use AssertsProblemDetails;
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_staff_can_check_in_using_order_reference(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $eventId = $scan['fixture']['event']->id;
        $orderReference = Order::query()
            ->where('tenant_id', $scan['fixture']['tenant']->id)
            ->where('event_id', $eventId)
            ->value('public_reference');

        self::assertIsString($orderReference);
        self::assertStringStartsWith('ord_', $orderReference);

        $url = "/api/v1/tenant/events/{$eventId}/scans";

        $this->actingAsScanner($scan);
        $this->postJson($url, [
            'qr_payload' => $orderReference,
            'scanner_type' => 'staff_phone',
        ], $this->scanHeaders($scan, 'order-reference-scan'))
            ->assertOk()
            ->assertJsonPath('data.result', 'accepted');
    }

    public function test_order_reference_from_another_event_is_rejected_clearly(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $tenant = $scan['fixture']['tenant'];
        $scanEventId = $scan['fixture']['event']->id;
        $orderReference = 'ord_'.str_repeat('a', 32);

        $otherEvent = Event::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'other-'.Str::lower((string) Str::ulid()),
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

        Order::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $otherEvent->id,
            'public_reference' => $orderReference,
            'access_token_hash' => hash('sha256', 'test-access-token'),
            'status' => 'paid',
            'buyer_name_ciphertext' => 'cipher',
            'buyer_email_ciphertext' => 'cipher',
            'buyer_email_index' => hash('sha256', 'buyer@example.test'),
            'encryption_key_id' => 'phase1-test',
            'subtotal_minor' => 0,
            'tax_minor' => 0,
            'fees_minor' => 0,
            'total_minor' => 0,
            'currency' => 'SAR',
            'locale' => 'en',
            'paid_at' => now(),
        ]);

        $this->actingAsScanner($scan);
        $this->postJson("/api/v1/tenant/events/{$scanEventId}/scans", [
            'qr_payload' => $orderReference,
            'scanner_type' => 'staff_phone',
        ], $this->scanHeaders($scan, 'order-reference-wrong-event'))
            ->assertOk()
            ->assertJsonPath('data.result', 'rejected')
            ->assertJsonPath('data.reason_code', 'order_reference_wrong_event');
    }
}
