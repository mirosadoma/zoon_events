<?php

namespace App\Modules\Scanning\Application\Queries;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItem;
use App\Modules\Shared\Application\DataProtection\BlindIndex;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use Illuminate\Support\Collection;

/**
 * Lookup-safe attendee search for check-in desk and kiosk.
 *
 * Queries are strictly scoped to the caller's tenant+event so no cross-tenant
 * data can be surfaced. Name searches are bounded to `$maxMatches + 1` rows
 * so we can detect "too many" without an unbounded COUNT query.
 */
final readonly class LookupAttendeesQuery
{
    private const EMAIL_PATTERN = '/^[^@\s]+@[^@\s]+\.[^@\s]+$/';

    private const PHONE_PATTERN = '/^\+?[0-9\s\-]{6,20}$/';

    public function __construct(
        private BlindIndex $indexes,
        private PersonalDataCipher $cipher,
    ) {}

    /**
     * @param  int  $maxMatches  Maximum allowed matches before returning too_many.
     * @return array{too_many: bool, matches: list<array{attendee_id: string, credential_id: string|null, display_name: string, ticket_type_label: string, checkin_status: string}>}
     */
    public function search(string $tenantId, string $eventId, string $fragment, int $maxMatches = 8): array
    {
        $fragment = $this->normalizeFragment($fragment);
        if ($fragment === '') {
            return ['too_many' => false, 'matches' => []];
        }

        $base = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->orderBy('registered_at')
            ->orderBy('id');

        if (str_starts_with(mb_strtolower($fragment), 'ord_')) {
            $attendeeIds = $this->attendeeIdsForOrderReference($tenantId, $eventId, $fragment);
            if ($attendeeIds === []) {
                return ['too_many' => false, 'matches' => []];
            }

            $exact = (clone $base)
                ->whereIn('id', $attendeeIds)
                ->with('lastScanEvent')
                ->limit($maxMatches + 1)
                ->get();

            return $this->finalize($exact, $tenantId, $eventId, $maxMatches, filterNeedle: null);
        }

        $needle = mb_strtolower($fragment);
        $looksLikeEmail = str_contains($fragment, '@') || preg_match(self::EMAIL_PATTERN, $fragment) === 1;
        $looksLikePhone = preg_match(self::PHONE_PATTERN, $fragment) === 1;

        if ($looksLikeEmail) {
            $emailIndex = $this->indexes->email($fragment);
            $attendeeIds = Attendee::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('email_index', $emailIndex)
                ->pluck('id')
                ->all();

            $attendeeIds = array_values(array_unique([
                ...$attendeeIds,
                ...$this->attendeeIdsForBuyerEmailIndex($tenantId, $eventId, $emailIndex),
            ]));

            if ($attendeeIds !== []) {
                $exact = (clone $base)
                    ->whereIn('id', $attendeeIds)
                    ->with('lastScanEvent')
                    ->limit($maxMatches + 1)
                    ->get();

                if ($exact->isNotEmpty()) {
                    return $this->finalize($exact, $tenantId, $eventId, $maxMatches, filterNeedle: null);
                }
            }
        } elseif ($looksLikePhone) {
            $exact = (clone $base)
                ->where('phone_index', $this->indexes->phone($fragment))
                ->with('lastScanEvent')
                ->limit($maxMatches + 1)
                ->get();

            if ($exact->isNotEmpty()) {
                return $this->finalize($exact, $tenantId, $eventId, $maxMatches, filterNeedle: null);
            }
        }

        $rows = $base->with('lastScanEvent')->get();

        return $this->finalize($rows, $tenantId, $eventId, $maxMatches, filterNeedle: $needle);
    }

    public function emailDestinationForAttendee(string $tenantId, string $eventId, string $attendeeId): ?string
    {
        $attendee = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($attendeeId);

        if ($attendee === null || $attendee->email_ciphertext === null || $attendee->encryption_key_id === null) {
            return null;
        }

        return $this->decryptOrNull(
            $attendee->email_ciphertext,
            $attendee->encryption_key_id,
            $tenantId,
            $eventId,
        );
    }

    /**
     * @param  Collection<int, Attendee>  $rows
     * @return array{too_many: bool, matches: list<array{attendee_id: string, credential_id: string|null, display_name: string, ticket_type_label: string, checkin_status: string}>}
     */
    private function finalize(
        Collection $rows,
        string $tenantId,
        string $eventId,
        int $maxMatches,
        ?string $filterNeedle,
    ): array {
        $mapped = $rows->map(function (Attendee $attendee) use ($tenantId, $eventId): array {
            $firstName = $this->decryptOrNull($attendee->first_name_ciphertext, $attendee->encryption_key_id, $tenantId, $eventId);
            $lastName = $this->decryptOrNull($attendee->last_name_ciphertext, $attendee->encryption_key_id, $tenantId, $eventId);
            $email = $this->decryptOrNull($attendee->email_ciphertext, $attendee->encryption_key_id, $tenantId, $eventId);
            $phone = $this->decryptOrNull($attendee->phone_ciphertext, $attendee->encryption_key_id, $tenantId, $eventId);
            $displayName = trim(($firstName ?? '').' '.($lastName ?? ''));

            $credential = Credential::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('attendee_id', $attendee->id)
                ->whereNull('superseded_by_id')
                ->where('status', '!=', 'revoked')
                ->latest('issued_at')
                ->first();

            return [
                'attendee_id' => (string) $attendee->id,
                'credential_id' => $credential?->id !== null ? (string) $credential->id : null,
                'display_name' => $displayName,
                'email' => $email ?? '',
                'phone' => $phone ?? '',
                'ticket_type_label' => (string) ($attendee->ticket_type_id ?? ''),
                'checkin_status' => (string) ($attendee->checkin_status ?? 'not_checked_in'),
            ];
        });

        if ($filterNeedle !== null && $filterNeedle !== '') {
            $mapped = $mapped->filter(function (array $row) use ($filterNeedle): bool {
                return str_contains(mb_strtolower($row['display_name']), $filterNeedle)
                    || str_contains(mb_strtolower($row['email']), $filterNeedle)
                    || str_contains(mb_strtolower($row['phone']), $filterNeedle);
            })->values();
        }

        if ($mapped->count() > $maxMatches) {
            return ['too_many' => true, 'matches' => []];
        }

        $matches = $mapped->map(function (array $row): array {
            unset($row['email'], $row['phone']);

            return $row;
        })->values()->all();

        return ['too_many' => false, 'matches' => $matches];
    }

    /** @return list<int|string> */
    private function attendeeIdsForOrderReference(string $tenantId, string $eventId, string $reference): array
    {
        $order = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('public_reference', $reference)
            ->first();

        if ($order === null) {
            return [];
        }

        return OrderItem::query()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $order->id)
            ->whereNotNull('attendee_id')
            ->pluck('attendee_id')
            ->all();
    }

    /** @return list<int|string> */
    private function attendeeIdsForBuyerEmailIndex(string $tenantId, string $eventId, string $emailIndex): array
    {
        $orderIds = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('buyer_email_index', $emailIndex)
            ->pluck('id')
            ->all();

        if ($orderIds === []) {
            return [];
        }

        return OrderItem::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('order_id', $orderIds)
            ->whereNotNull('attendee_id')
            ->pluck('attendee_id')
            ->all();
    }

    private function normalizeFragment(string $fragment): string
    {
        $fragment = trim($fragment);
        // Strip invisible / bidi marks often introduced by mobile copy-paste.
        $fragment = preg_replace('/[\x{200B}-\x{200F}\x{FEFF}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $fragment) ?? $fragment;

        return trim($fragment);
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
