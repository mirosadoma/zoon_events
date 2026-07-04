<?php

namespace Tests\Unit\Registration;

use App\Modules\Registration\Application\Validation\FormSchemaValidator;
use App\Modules\Registration\Application\Validation\FormVersionPublisher;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
final class RegistrationFormSchemaTest extends TestCase
{
    public function test_supported_fields_have_stable_canonical_hash(): void
    {
        $fields = $this->fields();
        $validator = new FormSchemaValidator;

        self::assertSame($validator->canonicalHash($fields), $validator->canonicalHash([
            ['label_ar' => 'البريد', 'required' => true, 'type' => 'email', 'key' => 'email', 'label_en' => 'Email'],
            ['condition' => ['value' => 'yes', 'operator' => 'equals', 'field' => 'email'], 'label_ar' => 'الاسم', 'type' => 'text', 'key' => 'display_name', 'label_en' => 'Display name'],
        ]));
    }

    public function test_conditions_may_reference_only_prior_fields_and_cannot_cycle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FormSchemaValidator)->validate([
            ['key' => 'first', 'type' => 'text', 'label_en' => 'First', 'label_ar' => 'الأول', 'condition' => ['field' => 'second', 'operator' => 'equals', 'value' => 'x']],
            ['key' => 'second', 'type' => 'text', 'label_en' => 'Second', 'label_ar' => 'الثاني', 'condition' => ['field' => 'first', 'operator' => 'equals', 'value' => 'x']],
        ]);
    }

    public function test_reserved_fields_are_rejected_even_when_marked_internal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FormSchemaValidator)->validate([
            ['key' => 'payment', 'type' => 'text', 'label_en' => 'Payment', 'label_ar' => 'الدفع', 'visibility' => 'internal'],
        ]);
    }

    public function test_publishing_requires_consent_versions(): void
    {
        $publisher = new FormVersionPublisher(new FormSchemaValidator);
        $prepared = $publisher->prepare($this->fields(), 'privacy-v1', 'terms-v1');
        self::assertSame(64, strlen($prepared['schema_hash']));

        $this->expectException(InvalidArgumentException::class);
        $publisher->prepare($this->fields(), '', 'terms-v1');
    }

    public function test_all_phase_one_types_and_server_owned_fields_are_supported(): void
    {
        $fields = [];
        foreach (['text', 'email', 'phone', 'number', 'date', 'checkbox', 'consent'] as $index => $type) {
            $fields[] = ['key' => "field_{$index}", 'type' => $type, 'label_en' => 'Field', 'label_ar' => 'حقل'];
        }
        $fields[] = ['key' => 'choice', 'type' => 'dropdown', 'label_en' => 'Choice', 'label_ar' => 'اختيار', 'options' => ['a', 'b']];
        $fields[] = ['key' => 'many', 'type' => 'multi_select', 'label_en' => 'Many', 'label_ar' => 'متعدد', 'options' => ['a', 'b']];
        $fields[] = ['key' => 'internal_note', 'type' => 'hidden', 'label_en' => 'Internal', 'label_ar' => 'داخلي', 'visibility' => 'internal', 'default' => 'safe'];

        (new FormSchemaValidator)->validate($fields);
        self::assertCount(10, $fields);
    }

    /** @return list<array<string,mixed>> */
    private function fields(): array
    {
        return [
            ['key' => 'email', 'type' => 'email', 'label_en' => 'Email', 'label_ar' => 'البريد', 'required' => true],
            ['key' => 'display_name', 'type' => 'text', 'label_en' => 'Display name', 'label_ar' => 'الاسم', 'condition' => ['field' => 'email', 'operator' => 'equals', 'value' => 'yes']],
        ];
    }
}
