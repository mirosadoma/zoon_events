<?php

namespace App\Modules\Registration\Application\Submission;

use App\Modules\Registration\Application\Support\RegistrationPhoneNormalizer;
use App\Modules\Registration\Contracts\SubmissionCreator;
use App\Modules\Registration\Domain\Fields\FormFieldChoiceOptions;
use App\Modules\Registration\Domain\Fields\FormFieldType;
use App\Modules\Registration\Domain\SubmissionRecord;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationSubmission;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final readonly class EncryptedSubmissionCreator implements SubmissionCreator
{
    public function __construct(private PersonalDataCipher $cipher) {}

    public function create(string $tenantId, string $eventId, string $formVersionId, string $idempotencyKey, array $answers, array $consent, string $locale): SubmissionRecord
    {
        if (count($consent) !== 3
            || array_diff(['terms', 'privacy', 'marketing'], array_keys($consent)) !== []
            || $consent['terms'] !== true
            || $consent['privacy'] !== true
            || ! is_bool($consent['marketing'])) {
            throw ValidationException::withMessages([
                'consents.terms' => ['You must accept the terms and privacy notice.'],
            ]);
        }
        $form = RegistrationFormVersion::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'published')
            ->findOrFail($formVersionId);
        $clientFields = collect($form->fields)->filter(
            fn (array $field): bool => ($field['visibility'] ?? 'public') === 'public'
                && ($field['type'] ?? '') !== FormFieldType::Hidden->value
                && ($field['type'] ?? '') !== FormFieldType::Consent->value,
        )->values();
        $answers = $this->validateAnswers($clientFields, $answers);
        $storedAnswers = [];
        $activeKeys = $clientFields
            ->filter(fn (array $field): bool => $this->conditionMatches($field, $answers))
            ->pluck('key')
            ->all();
        foreach ($form->fields as $field) {
            $key = $field['key'];
            if (($field['type'] ?? '') === FormFieldType::Consent->value) {
                $storedAnswers[$key] = true;
                continue;
            }
            $storedAnswers[$key] = in_array($key, $activeKeys, true)
                ? $answers[$key]
                : ($field['default'] ?? null);
        }
        $consent = [
            'privacy_notice_version' => $form->privacy_notice_version,
            'terms_version' => $form->terms_version,
            'choices' => $consent,
            'recorded_at' => now()->toIso8601String(),
        ];
        ksort($storedAnswers);
        $encrypted = $this->cipher->encrypt(json_encode($storedAnswers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), "{$tenantId}:{$eventId}:submission");
        $submission = RegistrationSubmission::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'form_version_id' => $formVersionId,
            'submission_key_hash' => hash('sha256', $idempotencyKey),
            'answers_ciphertext' => $encrypted['ciphertext'],
            'encryption_key_id' => $encrypted['key_id'],
            'consent_evidence' => $consent,
            'locale' => $locale,
            'submitted_at' => now(),
            'created_at' => now(),
        ]);

        return new SubmissionRecord($submission->id, $encrypted['key_id']);
    }

    /** @param array<string,mixed> $field @param array<string,mixed> $answers */
    private function conditionMatches(array $field, array $answers): bool
    {
        $condition = $field['condition'] ?? null;
        if (! is_array($condition)) {
            return true;
        }
        $actual = $answers[$condition['field'] ?? ''] ?? null;
        $expected = $condition['value'] ?? null;

        return match ($condition['operator'] ?? '') {
            'equals' => $actual === $expected,
            'not_equals' => $actual !== $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            default => false,
        };
    }

    /** @param Collection<int, array<string,mixed>> $fields @param array<string,mixed> $answers */
    private function validateAnswers(Collection $fields, array $answers): array
    {
        $allowedKeys = $fields->pluck('key')->all();
        $unknownKeys = array_diff(array_keys($answers), $allowedKeys);
        $errors = [];

        foreach ($unknownKeys as $key) {
            $errors["answers.{$key}"] = ['This field is not accepted.'];
        }

        $normalized = [];
        foreach ($fields as $field) {
            if (! $this->conditionMatches($field, $answers)) {
                continue;
            }

            $key = (string) $field['key'];
            $value = array_key_exists($key, $answers) ? $answers[$key] : null;
            $message = $this->validateValue($field, $value);

            if ($message !== null) {
                $errors["answers.{$key}"] = [$message];
                continue;
            }

            if ($value !== null && $value !== '' && $value !== []) {
                if (FormFieldType::from($field['type']) === FormFieldType::Phone && is_string($value)) {
                    $value = RegistrationPhoneNormalizer::normalize($value);
                }
                $normalized[$key] = $value;
            } elseif (($field['required'] ?? false) === false) {
                $normalized[$key] = $value;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /** @param array<string,mixed> $field */
    private function validateValue(array $field, mixed $value): ?string
    {
        if (($field['required'] ?? false) && ($value === null || $value === '' || $value === [] || $value === false)) {
            return 'is required.';
        }

        if ($value === null || $value === '') {
            return null;
        }

        $type = FormFieldType::from($field['type']);
        if ($type === FormFieldType::Phone && is_string($value)) {
            $value = RegistrationPhoneNormalizer::normalize($value);
            if ($value === '') {
                return ($field['required'] ?? false) ? 'is required.' : null;
            }
        }

        $valid = match ($type) {
            FormFieldType::Text => is_string($value),
            FormFieldType::Email => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            FormFieldType::Phone => is_string($value) && RegistrationPhoneNormalizer::isValid($value),
            FormFieldType::Number => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)),
            FormFieldType::Date => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1,
            FormFieldType::Select, FormFieldType::Radio => in_array(
                $value,
                FormFieldChoiceOptions::values((array) ($field['options'] ?? [])),
                true,
            ),
            FormFieldType::MultiSelect, FormFieldType::Checkbox => is_array($value) && array_diff(
                $value,
                FormFieldChoiceOptions::values((array) ($field['options'] ?? [])),
            ) === [],
            FormFieldType::Consent => is_bool($value),
            FormFieldType::Hidden => false,
        };

        if (! $valid) {
            return match ($type) {
                FormFieldType::Email => 'must be a valid email address.',
                FormFieldType::Phone => 'must be a valid phone number.',
                default => 'is invalid.',
            };
        }

        $rules = (array) ($field['validation'] ?? []);
        if (is_string($value) && mb_strlen($value) > (int) ($rules['max_length'] ?? 5000)) {
            return 'exceeds its maximum length.';
        }

        if ((is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)))
            && ((isset($rules['min']) && (float) $value < (float) $rules['min'])
                || (isset($rules['max']) && (float) $value > (float) $rules['max']))) {
            return 'is outside its allowed range.';
        }

        return null;
    }
}
