<?php

namespace App\Modules\AdminConsole\Application\Support;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Registration\Domain\Fields\FormFieldChoiceOptions;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationSubmission;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Support\Collection;

/**
 * Builds labeled registration-form answer rows for organizer attendee views.
 */
final readonly class AttendeeRegistrationAnswersPresenter
{
    public function __construct(
        private PersonalDataCipher $cipher,
    ) {}

    /**
     * @return list<array{key: string, label: array{en: string, ar: string}, value: array{en: string, ar: string}}>
     */
    public function forAttendee(Event $event, Attendee $attendee): array
    {
        return $this->forAttendees($event, collect([$attendee]))[(string) $attendee->id] ?? [];
    }

    /**
     * @param  Collection<int, Attendee>  $attendees
     * @return array<string, list<array{key: string, label: array{en: string, ar: string}, value: array{en: string, ar: string}}>>
     */
    public function forAttendees(Event $event, Collection $attendees): array
    {
        if ($attendees->isEmpty()) {
            return [];
        }

        $fields = $this->formFields($event);
        $submissions = $this->submissionsById($event, $attendees);
        $ticketLabels = $this->ticketLabels($event, $attendees);
        $rowsByAttendee = [];

        foreach ($attendees as $attendee) {
            $attendeeId = (string) $attendee->id;
            $rows = [];

            $ticket = $ticketLabels[$attendeeId] ?? null;
            if ($ticket !== null && $ticket !== '') {
                $rows[] = [
                    'key' => 'ticket_type',
                    'label' => ['en' => 'Ticket type', 'ar' => 'نوع التذكرة'],
                    'value' => ['en' => $ticket, 'ar' => $ticket],
                ];
            }

            $answers = $this->answersFor($event, $attendee, $submissions);
            foreach ($answers as $key => $raw) {
                if (! is_string($key) || $key === '' || FormFieldChoiceOptions::isLinkedTextAnswerKey($key)) {
                    continue;
                }

                $meta = $fields[$key] ?? null;
                $value = $this->displayAnswer($raw, $meta['options'] ?? [], $key, $answers);
                if ($value['en'] === '' && $value['ar'] === '') {
                    continue;
                }

                $rows[] = [
                    'key' => $key,
                    'label' => [
                        'en' => (string) ($meta['label_en'] ?? $this->humanize($key)),
                        'ar' => (string) ($meta['label_ar'] ?? $meta['label_en'] ?? $this->humanize($key)),
                    ],
                    'value' => $value,
                ];
            }

            $rowsByAttendee[$attendeeId] = $rows;
        }

        return $rowsByAttendee;
    }

    /**
     * @param  Collection<int, Attendee>  $attendees
     * @param  Collection<string, RegistrationSubmission>  $submissions
     * @return array<string, mixed>
     */
    private function answersFor(Event $event, Attendee $attendee, Collection $submissions): array
    {
        if ($attendee->submission_id === null) {
            return [];
        }

        $submission = $submissions->get((string) $attendee->submission_id);
        if ($submission === null || $submission->answers_ciphertext === null || $submission->answers_ciphertext === 'anonymized') {
            return [];
        }

        try {
            $json = $this->cipher->decrypt(
                ['key_id' => $submission->encryption_key_id, 'ciphertext' => $submission->answers_ciphertext],
                "{$event->tenant_id}:{$event->id}:submission",
            );
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  Collection<int, Attendee>  $attendees
     * @return Collection<string, RegistrationSubmission>
     */
    private function submissionsById(Event $event, Collection $attendees): Collection
    {
        $ids = $attendees
            ->pluck('submission_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return RegistrationSubmission::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(fn (RegistrationSubmission $submission): string => (string) $submission->id);
    }

    /**
     * @return array<string, array{
     *   label_en: string,
     *   label_ar: string,
     *   options: array<string, array{en: string, ar: string}>
     * }>
     */
    private function formFields(Event $event): array
    {
        $query = RegistrationFormVersion::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id);

        $version = $event->active_form_version_id !== null
            ? (clone $query)->whereKey($event->active_form_version_id)->first()
            : null;

        $version ??= (clone $query)->where('status', 'published')->latest('version')->first()
            ?? $query->latest('version')->first();

        if ($version === null || ! is_array($version->fields)) {
            return [];
        }

        $fields = [];
        foreach ($version->fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = is_string($field['key'] ?? null) ? trim($field['key']) : '';
            if ($key === '') {
                continue;
            }

            $options = [];
            foreach ((array) ($field['options'] ?? []) as $option) {
                if (! is_array($option)) {
                    continue;
                }

                $optionValue = trim((string) ($option['value'] ?? $option['id'] ?? ''));
                if ($optionValue === '') {
                    continue;
                }

                $labelEn = trim((string) ($option['label_en'] ?? ''));
                $labelAr = trim((string) ($option['label_ar'] ?? ''));
                $options[$optionValue] = [
                    'en' => $labelEn !== '' ? $labelEn : $optionValue,
                    'ar' => $labelAr !== '' ? $labelAr : ($labelEn !== '' ? $labelEn : $optionValue),
                ];
            }

            $fields[$key] = [
                'label_en' => is_string($field['label_en'] ?? null) ? $field['label_en'] : $key,
                'label_ar' => is_string($field['label_ar'] ?? null)
                    ? $field['label_ar']
                    : (is_string($field['label_en'] ?? null) ? $field['label_en'] : $key),
                'options' => $options,
            ];
        }

        return $fields;
    }

    /**
     * @param  array<string, array{en: string, ar: string}>  $options
     * @param  array<string, mixed>  $answers
     * @return array{en: string, ar: string}
     */
    private function displayAnswer(mixed $raw, array $options, string $fieldKey = '', array $answers = []): array
    {
        if ($raw === null) {
            return ['en' => '', 'ar' => ''];
        }

        if (is_bool($raw)) {
            $text = $raw ? 'Yes' : 'No';

            return ['en' => $text, 'ar' => $raw ? 'نعم' : 'لا'];
        }

        if (is_scalar($raw)) {
            return $this->resolveOptionLabelWithLinkedText(trim((string) $raw), $options, $fieldKey, $answers);
        }

        if (is_array($raw)) {
            $enParts = [];
            $arParts = [];
            foreach ($raw as $item) {
                if (! is_scalar($item)) {
                    continue;
                }

                $resolved = $this->resolveOptionLabelWithLinkedText(trim((string) $item), $options, $fieldKey, $answers);
                if ($resolved['en'] !== '') {
                    $enParts[] = $resolved['en'];
                }
                if ($resolved['ar'] !== '') {
                    $arParts[] = $resolved['ar'];
                }
            }

            return [
                'en' => implode(', ', $enParts),
                'ar' => implode(', ', $arParts),
            ];
        }

        return ['en' => '', 'ar' => ''];
    }

    /**
     * @param  array<string, array{en: string, ar: string}>  $options
     * @param  array<string, mixed>  $answers
     * @return array{en: string, ar: string}
     */
    private function resolveOptionLabelWithLinkedText(
        string $value,
        array $options,
        string $fieldKey,
        array $answers,
    ): array {
        $resolved = $this->resolveOptionLabel($value, $options);
        if ($resolved['en'] === '' && $resolved['ar'] === '') {
            return $resolved;
        }

        if ($fieldKey === '' || $value === '') {
            return $resolved;
        }

        $linkedKey = FormFieldChoiceOptions::linkedTextAnswerKey($fieldKey, $value);
        $linkedRaw = $answers[$linkedKey] ?? null;
        $linkedText = is_string($linkedRaw) ? trim($linkedRaw) : '';
        if ($linkedText === '') {
            return $resolved;
        }

        return [
            'en' => $resolved['en'].' ('.$linkedText.')',
            'ar' => $resolved['ar'].' ('.$linkedText.')',
        ];
    }

    /**
     * @param  array<string, array{en: string, ar: string}>  $options
     * @return array{en: string, ar: string}
     */
    private function resolveOptionLabel(string $value, array $options): array
    {
        if ($value === '') {
            return ['en' => '', 'ar' => ''];
        }

        if (isset($options[$value])) {
            return $options[$value];
        }

        return ['en' => $value, 'ar' => $value];
    }

    /**
     * @param  Collection<int, Attendee>  $attendees
     * @return array<string, string>
     */
    private function ticketLabels(Event $event, Collection $attendees): array
    {
        $ticketIds = $attendees
            ->pluck('ticket_type_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ticketIds === []) {
            return [];
        }

        $tickets = TicketType::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->whereIn('id', $ticketIds)
            ->get()
            ->keyBy(fn (TicketType $ticket): string => (string) $ticket->id);

        $labels = [];
        foreach ($attendees as $attendee) {
            if ($attendee->ticket_type_id === null) {
                continue;
            }

            $ticket = $tickets->get((string) $attendee->ticket_type_id);
            if ($ticket === null) {
                continue;
            }

            $labels[(string) $attendee->id] = (string) ($ticket->name_en ?: $ticket->name_ar ?: $ticket->code ?: '');
        }

        return $labels;
    }

    private function humanize(string $key): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }
}
