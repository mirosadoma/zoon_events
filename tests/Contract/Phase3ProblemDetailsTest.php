<?php

namespace Tests\Contract;

use App\Modules\Shared\Http\Problems\Phase3Problem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-3')]
final class Phase3ProblemDetailsTest extends TestCase
{
    /** @return array<string, array{string, int}> */
    public static function errorCodeProvider(): array
    {
        return [
            'kiosk_session_invalid' => ['kiosk_session_invalid', 401],
            'kiosk_session_unconfirmed' => ['kiosk_session_unconfirmed', 401],
            'kiosk_retired' => ['kiosk_retired', 401],
            'lookup_too_many_matches' => ['lookup_too_many_matches', 422],
            'lookup_confirmation_required' => ['lookup_confirmation_required', 422],
            'lookup_confirmation_invalid' => ['lookup_confirmation_invalid', 422],
            'badge_template_not_active' => ['badge_template_not_active', 409],
            'badge_template_invalid_field' => ['badge_template_invalid_field', 422],
            'badge_reprint_reason_required' => ['badge_reprint_reason_required', 422],
            'badge_reprint_not_permitted' => ['badge_reprint_not_permitted', 403],
            'badge_no_prior_print_job' => ['badge_no_prior_print_job', 409],
            'badge_print_not_permitted' => ['badge_print_not_permitted', 403],
            'printer_unavailable' => ['printer_unavailable', 503],
            'printer_error' => ['printer_error', 409],
            'payload_rejected' => ['payload_rejected', 422],
            'checkin_desk_not_permitted' => ['checkin_desk_not_permitted', 403],
            'walk_up_registration_disabled' => ['walk_up_registration_disabled', 403],
            'walk_up_payment_not_collectible' => ['walk_up_payment_not_collectible', 422],
        ];
    }

    #[DataProvider('errorCodeProvider')]
    public function test_error_code_has_correct_http_status(string $code, int $expectedStatus): void
    {
        $exception = Phase3Problem::make($code);

        self::assertSame($expectedStatus, $exception->status, "Status mismatch for {$code}");
    }

    #[DataProvider('errorCodeProvider')]
    public function test_error_code_has_english_and_arabic_messages(string $code, int $expectedStatus): void
    {
        $en = require base_path('lang/en/phase3.php');
        $ar = require base_path('lang/ar/phase3.php');

        self::assertArrayHasKey($code, $en, "Missing English message for {$code}");
        self::assertArrayHasKey($code, $ar, "Missing Arabic message for {$code}");
        self::assertNotEmpty($en[$code], "Empty English message for {$code}");
        self::assertNotEmpty($ar[$code], "Empty Arabic message for {$code}");
    }

    #[DataProvider('errorCodeProvider')]
    public function test_error_message_does_not_contain_secrets_or_pii(string $code, int $expectedStatus): void
    {
        $en = require base_path('lang/en/phase3.php');
        $ar = require base_path('lang/ar/phase3.php');

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
