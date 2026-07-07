<?php

namespace Tests\Contract;

use App\Modules\Shared\Http\Problems\Phase4Problem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-4')]
final class Phase4ProblemDetailsTest extends TestCase
{
    /** @return array<string, array{string, int}> */
    public static function errorCodeProvider(): array
    {
        return [
            'acs_integration_invalid' => ['acs_integration_invalid', 401],
            'acs_capability_denied' => ['acs_capability_denied', 403],
            'acs_zone_unmapped' => ['acs_zone_unmapped', 404],
            'acs_lane_unmapped' => ['acs_lane_unmapped', 404],
            'acs_event_out_of_scope' => ['acs_event_out_of_scope', 404],
            'acs_duplicate_external_id' => ['acs_duplicate_external_id', 409],
            'acs_invalid_time_window' => ['acs_invalid_time_window', 422],
            'acs_config_not_permitted' => ['acs_config_not_permitted', 403],
            'acs_events_not_permitted' => ['acs_events_not_permitted', 403],
            'acs_emergency_not_permitted' => ['acs_emergency_not_permitted', 403],
        ];
    }

    #[DataProvider('errorCodeProvider')]
    public function test_error_code_has_correct_http_status(string $code, int $expectedStatus): void
    {
        $exception = Phase4Problem::make($code);

        self::assertSame($expectedStatus, $exception->status, "Status mismatch for {$code}");
    }

    #[DataProvider('errorCodeProvider')]
    public function test_error_code_has_english_and_arabic_messages(string $code, int $expectedStatus): void
    {
        $en = require base_path('lang/en/phase4.php');
        $ar = require base_path('lang/ar/phase4.php');

        self::assertArrayHasKey($code, $en, "Missing English message for {$code}");
        self::assertArrayHasKey($code, $ar, "Missing Arabic message for {$code}");
        self::assertNotEmpty($en[$code], "Empty English message for {$code}");
        self::assertNotEmpty($ar[$code], "Empty Arabic message for {$code}");
    }

    #[DataProvider('errorCodeProvider')]
    public function test_error_message_does_not_contain_secrets_or_pii(string $code, int $expectedStatus): void
    {
        $en = require base_path('lang/en/phase4.php');
        $ar = require base_path('lang/ar/phase4.php');

        $sensitivePatterns = ['secret', 'token', 'password', 'connection', 'email@', 'phone', 'national_id'];

        foreach ([$en[$code] ?? '', $ar[$code] ?? ''] as $message) {
            foreach ($sensitivePatterns as $pattern) {
                self::assertStringNotContainsStringIgnoringCase(
                    $pattern,
                    $message,
                    "Message for {$code} contains sensitive pattern '{$pattern}'"
                );
            }
        }
    }
}
