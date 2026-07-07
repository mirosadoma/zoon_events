<?php

namespace App\Modules\Scanning\Application\Queries;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Shared\Application\DataProtection\BlindIndex;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;

/**
 * Lookup-safe attendee search for check-in desk and kiosk.
 *
 * Queries are strictly scoped to the caller's tenant+event so no cross-tenant
 * data can be surfaced. Name searches are bounded to `$maxMatches + 1` rows
 * so we can detect "too many" without an unbounded COUNT query.
 */
final readonly class LookupAttendeesQuery
{
    private const EMAIL_PATTERN = '/^[^@]+@[^@]+\.[^@]+$/';
    private const PHONE_PATTERN = '/^\+?[0-9\s\-]{6,20}$/';

    public function __construct(
        private BlindIndex $indexes,
        private PersonalDataCipher $cipher,
    ) {}

    /**
     * @param int $maxMatches Maximum allowed matches before returning too_many.
     * @return array{too_many: bool, matches: list<array{attendee_id: string, credential_id: string|null, display_name: string, ticket_type_label: string, checkin_status: string}>}
     */
    public function search(string $tenantId, string $eventId, string $fragment, int $maxMatches = 8): array
    {
        $query = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->orderBy('registered_at')
            ->orderBy('id');

        $needle = mb_strtolower(trim($fragment));
        $isNameSearch = true;

        if (preg_match(self::EMAIL_PATTERN, $fragment)) {
            $query->where('email_index', $this->indexes->email($fragment));
            $isNameSearch = false;
        } elseif (preg_match(self::PHONE_PATTERN, $fragment)) {
            $query->where('phone_index', $this->indexes->phone($fragment));
            $isNameSearch = false;
        }

        $rows = $query->with('lastScanEvent')->get();

        if (! $isNameSearch && $rows->count() > $maxMatches) {
            return ['too_many' => true, 'matches' => []];
        }

        $mapped = $rows->map(function (Attendee $attendee) use ($tenantId, $eventId): array {
            $firstName = $this->decryptOrNull($attendee->first_name_ciphertext, $attendee->encryption_key_id, $tenantId, $eventId);
            $lastName  = $this->decryptOrNull($attendee->last_name_ciphertext, $attendee->encryption_key_id, $tenantId, $eventId);
            $displayName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));

            $credential = Credential::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('attendee_id', $attendee->id)
                ->whereNull('superseded_by_id')
                ->where('status', '!=', 'revoked')
                ->latest('issued_at')
                ->first();

            return [
                'attendee_id'       => $attendee->id,
                'credential_id'     => $credential?->id,
                'display_name'      => $displayName,
                'ticket_type_label' => (string) ($attendee->ticket_type_id ?? ''),
                'checkin_status'    => (string) ($attendee->checkin_status ?? 'not_checked_in'),
            ];
        });

        if ($isNameSearch) {
            $filtered = $mapped->filter(
                fn (array $row): bool => $needle !== '' && str_contains(mb_strtolower($row['display_name']), $needle)
            )->values();

            if ($filtered->count() > $maxMatches) {
                return ['too_many' => true, 'matches' => []];
            }

            return ['too_many' => false, 'matches' => $filtered->all()];
        }

        return ['too_many' => false, 'matches' => $mapped->values()->all()];
    }

    private function decryptOrNull(?string $ciphertext, ?string $keyId, string $tenantId, ?string $eventId = null): ?string
    {
        if ($ciphertext === null || $keyId === null) {
            return null;
        }

        try {
            $scope = $eventId === null ? $tenantId : "{$tenantId}:{$eventId}:attendee";

            return $this->cipher->decrypt(['ciphertext' => $ciphertext, 'key_id' => $keyId], $scope);
        } catch (\Throwable) {
            return null;
        }
    }
}
