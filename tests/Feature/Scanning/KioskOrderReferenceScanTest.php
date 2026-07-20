<?php

namespace Tests\Feature\Scanning;

use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\KioskSession;
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
#[Group('kiosk')]
final class KioskOrderReferenceScanTest extends Phase2MySqlTestCase
{
    use AssertsProblemDetails;
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_kiosk_can_check_in_using_order_reference(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $tenantId = $scan['fixture']['tenant']->id;
        $eventId = $scan['fixture']['event']->id;

        $orderReference = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->value('public_reference');

        self::assertIsString($orderReference);
        self::assertStringStartsWith('ord_', $orderReference);

        $kiosk = Kiosk::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'device_name' => 'Order-ref kiosk',
            'device_code' => 'ORDREF01',
            'status' => 'online',
            'printer_status' => 'unknown',
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

        $this->withHeaders([
            'Authorization' => 'KioskSession '.$secret,
            'Idempotency-Key' => 'kiosk-order-ref-'.Str::lower((string) Str::ulid()),
            'Accept' => 'application/json',
        ])->postJson('/api/v1/kiosk/v1/scans', [
            'qr_payload' => $orderReference,
        ])
            ->assertOk()
            ->assertJsonPath('data.result', 'accepted');
    }
}
