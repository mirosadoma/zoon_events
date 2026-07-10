<?php

namespace Tests\Contract;

use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Shared\Http\Problems\ProblemFactory;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
final class Phase1ProblemDetailsTest extends TestCase
{
    public function test_every_phase_one_code_has_equivalent_locales_and_safe_problem_details(): void
    {
        foreach (Phase1Problem::STATUS as $code => $status) {
            app()->setLocale('en');
            $english = __("phase1.{$code}");
            app()->setLocale('ar');
            $arabic = __("phase1.{$code}");
            self::assertIsString($english);
            self::assertIsString($arabic);
            self::assertNotSame($english, $arabic);

            $problem = ProblemFactory::fromThrowable(Phase1Problem::make($code), '/api/v1/test', 'correlation');
            self::assertSame($status, $problem->status);
            self::assertSame($code, $problem->code);
            self::assertStringNotContainsString('secret', mb_strtolower($problem->detail));
            self::assertStringNotContainsString('provider', mb_strtolower($problem->detail));
        }
    }
}
