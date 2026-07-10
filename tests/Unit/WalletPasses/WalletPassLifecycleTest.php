<?php

namespace Tests\Unit\WalletPasses;

use App\Modules\WalletPasses\Domain\WalletPassStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-2')]
#[Group('wallet-passes')]
final class WalletPassLifecycleTest extends TestCase
{
    public function test_allowed_status_transitions_match_the_phase_two_contract(): void
    {
        foreach ([
            [WalletPassStatus::Created, WalletPassStatus::Active],
            [WalletPassStatus::Active, WalletPassStatus::Updated],
            [WalletPassStatus::Updated, WalletPassStatus::Active],
            [WalletPassStatus::Active, WalletPassStatus::Revoked],
            [WalletPassStatus::Updated, WalletPassStatus::Revoked],
            [WalletPassStatus::Active, WalletPassStatus::Expired],
            [WalletPassStatus::Updated, WalletPassStatus::Expired],
            [WalletPassStatus::Created, WalletPassStatus::Failed],
        ] as [$from, $to]) {
            self::assertTrue($from->canTransitionTo($to), "{$from->value} -> {$to->value}");
        }
    }

    public function test_terminal_statuses_cannot_return_to_active(): void
    {
        foreach ([
            [WalletPassStatus::Revoked, WalletPassStatus::Active],
            [WalletPassStatus::Expired, WalletPassStatus::Active],
            [WalletPassStatus::Failed, WalletPassStatus::Active],
        ] as [$from, $to]) {
            self::assertFalse($from->canTransitionTo($to), "{$from->value} -> {$to->value}");
        }
    }
}
