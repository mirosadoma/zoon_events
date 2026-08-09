<?php

namespace Tests\Unit\Registration;

use App\Modules\Registration\Domain\Fields\FormFieldChoiceOptions;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
final class FormFieldChoiceOptionsTest extends TestCase
{
    public function test_normalize_preserves_linked_text_flag(): void
    {
        $normalized = FormFieldChoiceOptions::normalizeForStorage([
            ['value' => 'web', 'label_en' => 'Web', 'label_ar' => 'ويب'],
            ['value' => 'other', 'label_en' => 'Other', 'label_ar' => 'أخرى', 'linked_text' => true],
        ]);

        self::assertSame([
            [
                'value' => 'web',
                'label_en' => 'Web',
                'label_ar' => 'ويب',
            ],
            [
                'value' => 'other',
                'label_en' => 'Other',
                'label_ar' => 'أخرى',
                'linked_text' => true,
            ],
        ], $normalized);

        self::assertSame(['other'], FormFieldChoiceOptions::linkedTextValues($normalized));
        self::assertSame(
            'heard_about__other__linked_text',
            FormFieldChoiceOptions::linkedTextAnswerKey('heard_about', 'other'),
        );
    }
}
