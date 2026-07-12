<?php

namespace Tests\Unit\Registration;

use App\Modules\Registration\Domain\Fields\RegistrationSystemFields;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
final class RegistrationSystemFieldsTest extends TestCase
{
    public function test_enforce_prepends_system_fields_and_removes_duplicates(): void
    {
        $enforced = RegistrationSystemFields::enforce([
            ['key' => 'email', 'type' => 'text', 'label_en' => 'Custom email', 'label_ar' => 'بريد', 'required' => false],
            ['key' => 'company', 'type' => 'text', 'label_en' => 'Company', 'label_ar' => 'الشركة'],
        ]);

        self::assertSame(['full_name', 'email', 'phone', 'company'], array_column($enforced, 'key'));
        self::assertSame('Full name', $enforced[0]['label_en']);
        self::assertSame('Company', $enforced[3]['label_en']);
    }
}
