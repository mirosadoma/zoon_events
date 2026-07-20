<?php

namespace Tests\Unit\Events;

use App\Modules\Events\Domain\EventCodeGenerator;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
final class EventCodeGeneratorTest extends TestCase
{
    public function test_generates_eight_digit_numeric_codes(): void
    {
        $code = (new EventCodeGenerator)->generate();

        self::assertMatchesRegularExpression('/^\d{8}$/', $code);
    }
}
