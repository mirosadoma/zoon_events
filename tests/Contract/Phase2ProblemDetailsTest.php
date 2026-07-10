<?php

namespace Tests\Contract;

use App\Modules\Shared\Http\Problems\Phase2Problem;
use App\Modules\Shared\Http\Problems\ProblemFactory;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-2')]
final class Phase2ProblemDetailsTest extends TestCase
{
    public function test_every_phase_two_code_has_equivalent_locales_and_safe_problem_details(): void
    {
        foreach (Phase2Problem::STATUS as $code => $status) {
            app()->setLocale('en');
            $english = __("phase2.{$code}");
            app()->setLocale('ar');
            $arabic = __("phase2.{$code}");
            self::assertIsString($english);
            self::assertIsString($arabic);
            self::assertNotSame($english, $arabic);

            $problem = ProblemFactory::fromThrowable(Phase2Problem::make($code), '/api/v1/test', 'correlation');
            self::assertSame($status, $problem->status);
            self::assertSame($code, $problem->code);
            self::assertStringNotContainsString('secret', mb_strtolower($problem->detail));
            self::assertStringNotContainsString('provider', mb_strtolower($problem->detail));
        }
    }
}
