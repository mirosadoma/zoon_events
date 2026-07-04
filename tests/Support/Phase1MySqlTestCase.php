<?php

namespace Tests\Support;

use PHPUnit\Framework\Attributes\Group;

#[Group('phase-1')]
abstract class Phase1MySqlTestCase extends MySqlTestCase
{
    protected function assertSyntheticFixture(mixed ...$values): void
    {
        $serialized = mb_strtolower(json_encode($values, JSON_THROW_ON_ERROR));

        self::assertStringNotContainsString('@zonetec.', $serialized);
        self::assertStringNotContainsString('production', $serialized);
    }
}
