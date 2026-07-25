<?php

namespace Tests\Unit\Registration;

use App\Modules\Registration\Domain\Fields\RegistrationSystemFields;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
final class RegistrationSystemFieldsTest extends TestCase
{
    public function test_enforce_preserves_order_and_fills_missing_system_fields(): void
    {
        $enforced = RegistrationSystemFields::enforce([
            ['key' => 'company', 'type' => 'text', 'label_en' => 'Company', 'label_ar' => 'الشركة'],
            ['key' => 'email', 'type' => 'text', 'label_en' => 'Custom email', 'label_ar' => 'بريد', 'required' => false],
            ['key' => 'phone', 'type' => 'phone', 'label_en' => 'Phone number', 'label_ar' => 'رقم الجوال', 'required' => true, 'visibility' => 'public'],
        ]);

        self::assertSame(['company', 'email', 'phone', 'full_name'], array_column($enforced, 'key'));
        self::assertSame('Email', $enforced[1]['label_en']);
        self::assertSame('Company', $enforced[0]['label_en']);
        self::assertTrue($enforced[1]['required']);
    }

    public function test_assert_present_allows_system_fields_in_any_order(): void
    {
        RegistrationSystemFields::assertPresent([
            [
                'key' => 'phone',
                'type' => 'phone',
                'label_en' => 'Phone number',
                'label_ar' => 'رقم الجوال',
                'required' => true,
                'visibility' => 'public',
            ],
            ['key' => 'company', 'type' => 'text', 'label_en' => 'Company', 'label_ar' => 'الشركة'],
            [
                'key' => 'full_name',
                'type' => 'text',
                'label_en' => 'Full name',
                'label_ar' => 'الاسم الكامل',
                'required' => true,
                'visibility' => 'public',
            ],
            [
                'key' => 'email',
                'type' => 'email',
                'label_en' => 'Email',
                'label_ar' => 'البريد الإلكتروني',
                'required' => true,
                'visibility' => 'public',
            ],
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_assert_present_rejects_missing_system_field(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RegistrationSystemFields::assertPresent([
            [
                'key' => 'full_name',
                'type' => 'text',
                'label_en' => 'Full name',
                'label_ar' => 'الاسم الكامل',
                'required' => true,
                'visibility' => 'public',
            ],
            [
                'key' => 'email',
                'type' => 'email',
                'label_en' => 'Email',
                'label_ar' => 'البريد الإلكتروني',
                'required' => true,
                'visibility' => 'public',
            ],
        ]);
    }
}
