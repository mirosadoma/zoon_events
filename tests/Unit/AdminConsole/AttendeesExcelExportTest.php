<?php

namespace Tests\Unit\AdminConsole;

use App\Modules\AdminConsole\Application\Exports\AttendeesExcelExport;
use App\Modules\AdminConsole\Application\PersonalDataReader;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('admin-dashboard')]
class AttendeesExcelExportTest extends TestCase
{
    public function test_builds_valid_xlsx_zip_payload(): void
    {
        $key = base64_encode(str_repeat('k', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
        $cipher = new PersonalDataCipher('current', ['current' => $key]);
        $export = new AttendeesExcelExport(new PersonalDataReader($cipher));

        $attendee = new Attendee;
        $attendee->forceFill([
            'id' => 42,
            'checkin_status' => 'checked_in',
            'preferred_locale' => 'en',
            'registered_at' => now(),
        ]);

        $response = $export->download(
            new Collection([$attendee]),
            [42 => 'active'],
            'attendees-test.xlsx',
        );

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        self::assertSame('PK', substr($content, 0, 2));
        self::assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }
}
