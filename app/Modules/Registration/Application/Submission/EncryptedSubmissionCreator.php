<?php

namespace App\Modules\Registration\Application\Submission;

use App\Modules\Registration\Contracts\SubmissionCreator;
use App\Modules\Registration\Domain\Fields\FormFieldType;
use App\Modules\Registration\Domain\SubmissionRecord;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationSubmission;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use InvalidArgumentException;

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
            throw new InvalidArgumentException('Consent choices must be explicit and complete.');
        }
        $form = RegistrationFormVersion::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'published')
            ->findOrFail($formVersionId);
        $clientFields = collect($form->fields)->filter(
            fn (array $field): bool => ($field['visibility'] ?? 'public') === 'public'
                && ($field['type'] ?? '') !== FormFieldType::Hidden->value,
        )->values();
        $activeFields = $clientFields->filter(fn (array $field): bool => $this->conditionMatches($field, $answers));
        $allowed = $activeFields->pluck('key')->all();
        if (array_diff(array_keys($answers), $allowed) !== [] || array_diff($allowed, array_keys($answers)) !== []) {
            throw new InvalidArgumentException('Answers must match the exact published registration form.');
        }
        foreach ($activeFields as $field) {
            $this->validateValue($field, $answers[$field['key']] ?? null);
        }
        $storedAnswers = [];
        foreach ($form->fields as $field) {
            $key = $field['key'];
            $storedAnswers[$key] = in_array($key, $allowed, true)
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

    /** @param array<string,mixed> $field */
    private function validateValue(array $field, mixed $value): void
    {
        if (($field['required'] ?? false) && ($value === null || $value === '' || $value === [] || $value === false)) {
            throw new InvalidArgumentException('A required registration answer is missing.');
        }
        if ($value === null || $value === '') {
            return;
        }
        $type = FormFieldType::from($field['type']);
        $valid = match ($type) {
            FormFieldType::Text => is_string($value),
            FormFieldType::Email => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            FormFieldType::Phone => is_string($value) && preg_match('/^\+?[0-9]{8,15}$/', $value) === 1,
            FormFieldType::Number => is_int($value) || is_float($value),
            FormFieldType::Date => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1,
            FormFieldType::Select, FormFieldType::Dropdown => in_array($value, (array) ($field['options'] ?? []), true),
            FormFieldType::MultiSelect => is_array($value) && array_diff($value, (array) ($field['options'] ?? [])) === [],
            FormFieldType::Checkbox, FormFieldType::Consent => is_bool($value),
            FormFieldType::Hidden => false,
        };
        if (! $valid) {
            throw new InvalidArgumentException('A registration answer has an invalid value.');
        }
        $rules = (array) ($field['validation'] ?? []);
        if (is_string($value) && mb_strlen($value) > (int) ($rules['max_length'] ?? 5000)) {
            throw new InvalidArgumentException('A registration answer exceeds its maximum length.');
        }
        if ((is_int($value) || is_float($value))
            && ((isset($rules['min']) && $value < $rules['min'])
                || (isset($rules['max']) && $value > $rules['max']))) {
            throw new InvalidArgumentException('A registration answer is outside its numeric bounds.');
        }
    }
}
