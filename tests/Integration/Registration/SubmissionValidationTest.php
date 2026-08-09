<?php

namespace Tests\Integration\Registration;

use App\Modules\Registration\Application\Validation\FormSchemaValidator;
use App\Modules\Registration\Contracts\SubmissionCreator;
use App\Modules\Registration\Domain\Fields\RegistrationSystemFields;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationSubmission;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('free-registration')]
final class SubmissionValidationTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_exact_published_answers_are_encrypted_with_versioned_consent_evidence(): void
    {
        $fixture = $this->createRegistrationFixture();
        $record = app(SubmissionCreator::class)->create(
            $fixture['tenant']->id, $fixture['event']->id, $fixture['form']->id,
            'synthetic-key', [
                'full_name' => 'Synthetic Attendee',
                'email' => 'attendee@example.test',
                'phone' => '0501234567',
            ], ['terms' => true, 'privacy' => true, 'marketing' => false], 'en',
        );
        $submission = RegistrationSubmission::query()->findOrFail($record->id);
        self::assertStringNotContainsString('attendee@example.test', $submission->answers_ciphertext);
        self::assertSame('privacy-v1', $submission->consent_evidence['privacy_notice_version']);
        $plaintext = app(PersonalDataCipher::class)->decrypt(
            ['key_id' => $submission->encryption_key_id, 'ciphertext' => $submission->answers_ciphertext],
            "{$fixture['tenant']->id}:{$fixture['event']->id}:submission",
        );
        self::assertSame([
            'email' => 'attendee@example.test',
            'full_name' => 'Synthetic Attendee',
            'phone' => '0501234567',
        ], json_decode($plaintext, true));
    }

    public function test_unknown_or_missing_form_answers_fail_before_storage(): void
    {
        $fixture = $this->createRegistrationFixture();
        $this->expectException(ValidationException::class);
        app(SubmissionCreator::class)->create(
            $fixture['tenant']->id, $fixture['event']->id, $fixture['form']->id,
            'synthetic-key', ['hidden_admin' => true], ['terms' => true, 'privacy' => true, 'marketing' => false], 'en',
        );
    }

    public function test_internal_and_hidden_fields_are_server_owned_and_never_accepted_from_public_input(): void
    {
        $fixture = $this->createRegistrationFixture();
        $fields = [
            ...RegistrationSystemFields::definitions(),
            ['key' => 'source', 'type' => 'hidden', 'label_en' => 'Source', 'label_ar' => 'المصدر', 'visibility' => 'internal', 'default' => 'public-form'],
        ];
        DB::table('registration_form_versions')->where('id', $fixture['form']->id)->update([
            'fields' => json_encode($fields, JSON_THROW_ON_ERROR),
            'schema_hash' => app(FormSchemaValidator::class)->canonicalHash($fields),
        ]);
        $public = $this->getJson("http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registration-form")
            ->assertOk();
        self::assertSame(['full_name', 'email', 'phone'], collect($public->json('data.form.fields'))->pluck('key')->all());

        $record = app(SubmissionCreator::class)->create(
            $fixture['tenant']->id, $fixture['event']->id, $fixture['form']->id,
            'server-owned', [
                'full_name' => 'Synthetic Attendee',
                'email' => 'attendee@example.test',
                'phone' => '0501234567',
            ], ['terms' => true, 'privacy' => true, 'marketing' => false], 'en',
        );
        $submission = RegistrationSubmission::query()->findOrFail($record->id);
        $plaintext = app(PersonalDataCipher::class)->decrypt(
            ['key_id' => $submission->encryption_key_id, 'ciphertext' => $submission->answers_ciphertext],
            "{$fixture['tenant']->id}:{$fixture['event']->id}:submission",
        );
        self::assertSame([
            'email' => 'attendee@example.test',
            'full_name' => 'Synthetic Attendee',
            'phone' => '0501234567',
            'source' => 'public-form',
        ], json_decode($plaintext, true));

        $this->expectException(ValidationException::class);
        app(SubmissionCreator::class)->create(
            $fixture['tenant']->id, $fixture['event']->id, $fixture['form']->id,
            'forged-internal',
            [
                'full_name' => 'Synthetic Attendee',
                'email' => 'attendee@example.test',
                'phone' => '0501234567',
                'source' => 'forged',
            ],
            ['terms' => true, 'privacy' => true, 'marketing' => false],
            'en',
        );
    }

    public function test_linked_text_option_is_required_and_stored_with_choice_answer(): void
    {
        $fixture = $this->createRegistrationFixture();
        $fields = [
            ...RegistrationSystemFields::definitions(),
            [
                'key' => 'heard_about',
                'type' => 'radio',
                'label_en' => 'How did you hear about us?',
                'label_ar' => 'كيف سمعت عنا؟',
                'required' => true,
                'visibility' => 'public',
                'options' => [
                    ['value' => 'friend', 'label_en' => 'Friend', 'label_ar' => 'صديق'],
                    ['value' => 'other', 'label_en' => 'Other', 'label_ar' => 'أخرى', 'linked_text' => true],
                ],
            ],
        ];
        DB::table('registration_form_versions')->where('id', $fixture['form']->id)->update([
            'fields' => json_encode($fields, JSON_THROW_ON_ERROR),
            'schema_hash' => app(FormSchemaValidator::class)->canonicalHash($fields),
        ]);

        try {
            app(SubmissionCreator::class)->create(
                $fixture['tenant']->id,
                $fixture['event']->id,
                $fixture['form']->id,
                'linked-text-missing',
                [
                    'full_name' => 'Synthetic Attendee',
                    'email' => 'attendee@example.test',
                    'phone' => '0501234567',
                    'heard_about' => 'other',
                ],
                ['terms' => true, 'privacy' => true, 'marketing' => false],
                'en',
            );
            self::fail('Expected ValidationException when linked text is missing.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('answers.heard_about__other__linked_text', $exception->errors());
        }

        $record = app(SubmissionCreator::class)->create(
            $fixture['tenant']->id,
            $fixture['event']->id,
            $fixture['form']->id,
            'linked-text-ok',
            [
                'full_name' => 'Synthetic Attendee',
                'email' => 'attendee@example.test',
                'phone' => '0501234567',
                'heard_about' => 'other',
                'heard_about__other__linked_text' => 'Conference booth',
            ],
            ['terms' => true, 'privacy' => true, 'marketing' => false],
            'en',
        );

        $submission = RegistrationSubmission::query()->findOrFail($record->id);
        $plaintext = app(PersonalDataCipher::class)->decrypt(
            ['key_id' => $submission->encryption_key_id, 'ciphertext' => $submission->answers_ciphertext],
            "{$fixture['tenant']->id}:{$fixture['event']->id}:submission",
        );

        self::assertSame([
            'email' => 'attendee@example.test',
            'full_name' => 'Synthetic Attendee',
            'heard_about' => 'other',
            'heard_about__other__linked_text' => 'Conference booth',
            'phone' => '0501234567',
        ], json_decode($plaintext, true));
    }
}
