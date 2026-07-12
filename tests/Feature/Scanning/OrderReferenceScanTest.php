<?php

namespace Tests\Feature\Scanning;

use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
}
