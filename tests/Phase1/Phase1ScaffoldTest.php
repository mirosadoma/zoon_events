<?php

namespace Tests\Phase1;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
final class Phase1ScaffoldTest extends TestCase
{
    public function test_phase_one_configuration_is_registered(): void
    {
        self::assertSame(15, config('registration.hold_minutes'));
        self::assertSame('zt1', config('credentials.token_version'));
        self::assertSame('fake', config('payments.default'));
    }
}
