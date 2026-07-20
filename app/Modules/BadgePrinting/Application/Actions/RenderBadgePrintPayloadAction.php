<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\BadgePrinting\Domain\ValueObjects\PrintPayload;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Credentials\Application\Presentation\CredentialPresentationToken;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategoryVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationSubmission;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class RenderBadgePrintPayloadAction
{
    public function __construct(
        private PersonalDataCipher $cipher,
        private CredentialPresentationToken $presentationTokens,
    ) {}

    public function execute(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $credentialId,
        BadgeTemplate $template,
    ): PrintPayload {
        $layout = (array) $template->layout;
        $context = $this->loadContext($tenantId, $eventId, $attendeeId, $credentialId);
        $fields = [];

        foreach ($this->fieldKeys($layout) as $fieldKey) {
            $fields[$fieldKey] = match ($fieldKey) {
                'attendee_name' => $context['attendee_name'],
                'qr' => $context['qr'],
                'ticket_type' => $context['ticket_type'],
                'attendee_type' => $context['attendee_type'],
                'company' => $this->answer($context['answers'], ['company', 'organisation', 'organization', 'company_name', 'org']),
                'job_title' => $this->answer($context['answers'], ['job_title', 'jobTitle', 'title', 'position', 'job', 'job_role']),
                'tier' => $context['tier'],
                'zone' => $context['zone'],
                'color_code' => $context['color_code'],
                'organizer_logo_ref' => $context['organizer_logo_ref'],
                'sponsor_logo_ref' => $context['sponsor_logo_ref'],
                'custom_text' => null,
                default => null,
            };
        }

        return new PrintPayload(
            fields: $fields,
            paperSize: $template->paper_size,
            printerType: $template->printer_type,
            idempotencyKey: Str::uuid()->toString(),
        );
    }

    /**
     * @return array{
     *   attendee_name:?string,
     *   qr:?string,
     *   ticket_type:?string,
     *   attendee_type:?string,
     *   answers:array<string,mixed>,
     *   tier:?string,
     *   zone:?string,
     *   color_code:?string,
     *   organizer_logo_ref:?string,
     *   sponsor_logo_ref:?string
     * }
     */
    private function loadContext(string $tenantId, string $eventId, string $attendeeId, string $credentialId): array
    {
        $attendee = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($attendeeId);

        $credential = Credential::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($credentialId);

        $answers = $this->resolveAnswers($tenantId, $eventId, $attendee);
        $category = $this->resolveCategory($tenantId, $eventId, $attendee);
        $theme = $this->resolveTheme($tenantId, $eventId);
        $locale = $attendee?->preferred_locale === 'ar' ? 'ar' : 'en';

        return [
            'attendee_name' => $this->resolveAttendeeName($tenantId, $eventId, $attendee),
            'qr' => $this->resolveQrPayload($credential),
            'ticket_type' => $this->resolveTicketType($tenantId, $eventId, $attendee, $credential),
            'attendee_type' => $this->resolveAttendeeType($eventId, $attendee),
            'answers' => $answers,
            'tier' => $this->resolveTierLabel($category, $locale),
            'zone' => $this->resolveZone($category, $attendee, $locale),
            'color_code' => $category?->color,
            'organizer_logo_ref' => $this->publicUrl(is_string($theme['logo_path'] ?? null) ? $theme['logo_path'] : null),
            'sponsor_logo_ref' => $this->publicUrl(is_string($theme['sponsor_logo_path'] ?? null) ? $theme['sponsor_logo_path'] : null),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $layout
     * @return list<string>
     */
    private function fieldKeys(array $layout): array
    {
        if ($layout === []) {
            return [];
        }

        if (array_is_list($layout)) {
            $keys = [];
            foreach ($layout as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $field = $item['field'] ?? null;
                if (is_string($field) && $field !== '') {
                    $keys[] = $field;
                }
            }

            return array_values(array_unique($keys));
        }

        return array_map(strval(...), array_keys($layout));
    }

    private function resolveAttendeeName(string $tenantId, string $eventId, ?Attendee $attendee): ?string
    {
        if ($attendee === null) {
            return null;
        }

        $scope = "{$tenantId}:{$eventId}:attendee";

        try {
            $first = $this->cipher->decrypt(
                ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->first_name_ciphertext],
                $scope,
            );
            $last = $this->cipher->decrypt(
                ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->last_name_ciphertext],
                $scope,
            );

            return trim($first.' '.$last);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveAttendeeEmail(string $tenantId, string $eventId, ?Attendee $attendee): ?string
    {
        if ($attendee === null || $attendee->email_ciphertext === null) {
            return null;
        }

        $scope = "{$tenantId}:{$eventId}:attendee";

        try {
            return strtolower(trim($this->cipher->decrypt(
                ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->email_ciphertext],
                $scope,
            )));
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveAttendeeType(string $eventId, ?Attendee $attendee): ?string
    {
        if ($attendee === null) {
            return null;
        }

        $email = $this->resolveAttendeeEmail((string) $attendee->tenant_id, (string) $attendee->event_id, $attendee);
        if ($email === null || $email === '') {
            return 'Public';
        }

        $registeredViaInvite = EventRegistrationInvite::query()
            ->where('event_id', $eventId)
            ->where('email', $email)
            ->whereNotNull('used_at')
            ->exists();

        return $registeredViaInvite ? 'Private' : 'Public';
    }

    private function resolveQrPayload(?Credential $credential): ?string
    {
        if ($credential === null) {
            return null;
        }

        try {
            return $this->presentationTokens->resolve($credential);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveTicketType(
        string $tenantId,
        string $eventId,
        ?Attendee $attendee,
        ?Credential $credential,
    ): ?string {
        $ticketTypeId = $credential?->ticket_type_id ?? $attendee?->ticket_type_id;
        if ($ticketTypeId === null) {
            return null;
        }

        $ticket = TicketType::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($ticketTypeId);

        if ($ticket === null) {
            return (string) $ticketTypeId;
        }

        return $ticket->name_en ?: (string) $ticketTypeId;
    }

    private function resolveTierLabel(?EventCategory $category, string $locale): ?string
    {
        if ($category === null) {
            return null;
        }

        if ($locale === 'ar') {
            return $category->name_ar ?: $category->name;
        }

        return $category->name ?: $category->name_ar;
    }

    /** @return array<string, mixed> */
    private function resolveAnswers(string $tenantId, string $eventId, ?Attendee $attendee): array
    {
        if ($attendee?->submission_id === null) {
            return [];
        }

        $submission = RegistrationSubmission::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($attendee->submission_id);

        if ($submission === null || $submission->answers_ciphertext === null || $submission->answers_ciphertext === 'anonymized') {
            return [];
        }

        try {
            $json = $this->cipher->decrypt(
                ['key_id' => $submission->encryption_key_id, 'ciphertext' => $submission->answers_ciphertext],
                "{$tenantId}:{$eventId}:submission",
            );
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolveCategory(string $tenantId, string $eventId, ?Attendee $attendee): ?EventCategory
    {
        if ($attendee?->order_id === null) {
            return null;
        }

        $order = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($attendee->order_id);

        if ($order?->event_category_id === null) {
            return null;
        }

        return EventCategory::query()
            ->where('event_id', $eventId)
            ->find($order->event_category_id);
    }

    private function resolveZone(?EventCategory $category, ?Attendee $attendee, string $locale): ?string
    {
        if ($attendee?->event_venue_id !== null) {
            $venue = EventVenue::query()->find($attendee->event_venue_id);
            if ($venue !== null) {
                return $locale === 'ar'
                    ? ($venue->name_ar ?: $venue->name_en)
                    : ($venue->name_en ?: $venue->name_ar);
            }
        }

        if ($category === null) {
            return null;
        }

        $link = EventCategoryVenue::query()
            ->where('event_category_id', $category->id)
            ->orderBy('sort_order')
            ->first();

        if ($link === null) {
            return null;
        }

        $venue = EventVenue::query()->find($link->event_venue_id);
        if ($venue === null) {
            return null;
        }

        return $locale === 'ar'
            ? ($venue->name_ar ?: $venue->name_en)
            : ($venue->name_en ?: $venue->name_ar);
    }

    /** @return array<string, mixed> */
    private function resolveTheme(string $tenantId, string $eventId): array
    {
        $branding = EventBranding::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        return is_array($branding?->theme_config) ? $branding->theme_config : [];
    }

    private function publicUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $url = Storage::disk('public')->url($path);
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  string|list<string>  $keys
     */
    private function answer(array $answers, string|array $keys): ?string
    {
        $wanted = array_map(static fn (string $key): string => strtolower($key), (array) $keys);

        foreach ($answers as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (! in_array($normalizedKey, $wanted, true)) {
                continue;
            }

            $resolved = $this->stringifyAnswerValue($value);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        foreach ($answers as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            foreach ($wanted as $needle) {
                if ($needle !== '' && str_contains($normalizedKey, $needle)) {
                    $resolved = $this->stringifyAnswerValue($value);
                    if ($resolved !== null) {
                        return $resolved;
                    }
                }
            }
        }

        return null;
    }

    private function stringifyAnswerValue(mixed $value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            if (isset($value['value'])) {
                return $this->stringifyAnswerValue($value['value']);
            }
            if (isset($value['label'])) {
                return $this->stringifyAnswerValue($value['label']);
            }
            if (array_is_list($value)) {
                $parts = [];
                foreach ($value as $item) {
                    $part = $this->stringifyAnswerValue($item);
                    if ($part !== null) {
                        $parts[] = $part;
                    }
                }

                return $parts === [] ? null : implode(', ', $parts);
            }
        }

        return null;
    }
}
