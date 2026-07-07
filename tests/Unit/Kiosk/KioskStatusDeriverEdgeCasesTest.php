<?php

namespace Tests\Unit\Kiosk;

use App\Modules\Kiosk\Domain\KioskStatusDeriver;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('kiosk-health')]
#[Group('phase-3')]
final class KioskStatusDeriverEdgeCasesTest extends TestCase
{
    private KioskStatusDeriver $deriver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deriver = new KioskStatusDeriver;
    }

    public function test_retired_kiosk_always_derives_retired_even_with_fresh_heartbeat(): void
    {
        $kiosk = new Kiosk;
        $kiosk->forceFill([
            'status' => 'retired',
            'printer_status' => 'ready',
            'last_heartbeat_at' => CarbonImmutable::now(),
        ]);

        $derived = $this->deriver->derive($kiosk, 120, CarbonImmutable::now());

        self::assertSame('retired', $derived, 'Retired kiosk must always derive retired');
    }

    public function test_printer_error_with_fresh_heartbeat_derives_degraded_not_online(): void
    {
        $kiosk = new Kiosk;
        $kiosk->forceFill([
            'status' => 'online',
            'printer_status' => 'error',
            'last_heartbeat_at' => CarbonImmutable::now(),
        ]);

        $derived = $this->deriver->derive($kiosk, 120, CarbonImmutable::now());

        self::assertSame('degraded', $derived, 'Printer error with fresh heartbeat must derive degraded');
    }

    public function test_null_heartbeat_derives_offline(): void
    {
        $kiosk = new Kiosk;
        $kiosk->forceFill([
            'status' => 'registered',
            'printer_status' => 'unknown',
            'last_heartbeat_at' => null,
        ]);

        $derived = $this->deriver->derive($kiosk, 120, CarbonImmutable::now());

        self::assertSame('offline', $derived);
    }

    public function test_stale_heartbeat_derives_offline(): void
    {
        $kiosk = new Kiosk;
        $kiosk->forceFill([
            'status' => 'online',
            'printer_status' => 'ready',
            'last_heartbeat_at' => CarbonImmutable::now()->subSeconds(200),
        ]);

        $derived = $this->deriver->derive($kiosk, 120, CarbonImmutable::now());

        self::assertSame('offline', $derived);
    }

    public function test_fresh_heartbeat_with_ready_printer_derives_online(): void
    {
        $kiosk = new Kiosk;
        $kiosk->forceFill([
            'status' => 'online',
            'printer_status' => 'ready',
            'last_heartbeat_at' => CarbonImmutable::now(),
        ]);

        $derived = $this->deriver->derive($kiosk, 120, CarbonImmutable::now());

        self::assertSame('online', $derived);
    }
}
