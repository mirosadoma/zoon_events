<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use PHPUnit\Framework\TestCase;

class Phase6ProblemTest extends TestCase
{
    public function test_reason_codes_match_the_openapi_problem_enum(): void
    {
        $openApi = file_get_contents(__DIR__.'/../../../specs/009-venue-marketplace/contracts/openapi.yaml');
        self::assertIsString($openApi);
        self::assertMatchesRegularExpression(
            '/reason_code:\s*\n\s*type: string\s*\n\s*enum:\s*\n(?<enum>(?:\s+- [a-z0-9_]+\s*\n)+)/',
            $openApi,
        );
        preg_match(
            '/reason_code:\s*\n\s*type: string\s*\n\s*enum:\s*\n(?<enum>(?:\s+- [a-z0-9_]+\s*\n)+)/',
            $openApi,
            $match,
        );
        preg_match_all('/-\s+([a-z0-9_]+)/', $match['enum'], $codes);

        self::assertEqualsCanonicalizing($codes[1], Phase6Problem::reasonCodes());
    }

    public function test_every_problem_has_stable_status_safe_detail_and_english_fallback(): void
    {
        foreach (Phase6Problem::reasonCodes() as $reasonCode) {
            $status = Phase6Problem::statusFor($reasonCode);
            self::assertGreaterThanOrEqual(400, $status, $reasonCode);
            self::assertLessThanOrEqual(599, $status, $reasonCode);

            $detail = Phase6Problem::detailFor($reasonCode, 'unknown');
            self::assertSame(Phase6Problem::detailFor($reasonCode, 'en'), $detail);
            self::assertStringNotContainsString('secret', strtolower($detail));
            self::assertStringNotContainsString('credential', strtolower($detail));
            self::assertStringNotContainsString('token', strtolower($detail));
            self::assertStringNotContainsString('binding', strtolower($detail));
        }
    }
}
