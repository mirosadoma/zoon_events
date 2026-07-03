<?php

namespace Tests\Unit\FeatureFlags;

use App\Modules\FeatureFlags\Domain\FeatureFlagValueType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('feature-flags')]
class FeatureFlagValueTest extends TestCase
{
    public function test_values_must_match_declared_types(): void
    {
        self::assertTrue(FeatureFlagValueType::Boolean->accepts(false));
        self::assertFalse(FeatureFlagValueType::Boolean->accepts(0));
        self::assertTrue(FeatureFlagValueType::Integer->accepts(0));
        self::assertFalse(FeatureFlagValueType::Integer->accepts('0'));
        self::assertTrue(FeatureFlagValueType::String->accepts('safe'));
        self::assertFalse(FeatureFlagValueType::String->accepts(str_repeat('a', 501)));
    }
}
