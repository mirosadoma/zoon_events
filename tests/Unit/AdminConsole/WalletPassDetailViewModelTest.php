<?php

namespace Tests\Unit\AdminConsole;

use App\Modules\AdminConsole\ViewModels\Wallet\WalletPassDetailViewModel;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('admin-dashboard')]
final class WalletPassDetailViewModelTest extends TestCase
{
    public function test_detail_casts_ids_as_strings(): void
    {
        $event = new Event([
            'id' => 1,
            'name_en' => 'Summit',
            'name_ar' => 'القمة',
        ]);
        $event->id = 1;

        $pass = new WalletPass([
            'tenant_id' => 1,
            'event_id' => 1,
            'attendee_id' => 3,
            'credential_id' => 9,
            'provider' => 'apple',
            'pass_serial_number' => 'SERIAL-7',
            'pass_url' => 'https://wallet.test/apple/serial-7',
            'status' => WalletPassStatus::Active,
            'last_pushed_at' => now(),
            'last_push_reason_code' => null,
        ]);
        $pass->id = 7;

        $payload = (new WalletPassDetailViewModel)->detail($event, $pass);

        self::assertSame('7', $payload['walletPass']['id']);
        self::assertSame('3', $payload['walletPass']['attendee_id']);
        self::assertSame('9', $payload['walletPass']['credential_id']);
        self::assertSame('SERIAL-7', $payload['walletPass']['serial']);
    }
}
