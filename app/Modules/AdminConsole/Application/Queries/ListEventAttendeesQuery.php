<?php

namespace App\Modules\AdminConsole\Application\Queries;

use App\Modules\AdminConsole\Application\PersonalDataReader;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use App\Modules\Shared\Application\DataProtection\BlindIndex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class ListEventAttendeesQuery
{
    private const EMAIL_PATTERN = '/^[^@]+@[^@]+\.[^@]+$/';

    private const PHONE_PATTERN = '/^\+?[0-9\s\-]{6,20}$/';

    private const SEARCH_SCAN_LIMIT = 2000;

    private const EXPORT_LIMIT = 5000;

    public const PER_PAGE = 15;

    public function __construct(
        private BlindIndex $indexes,
        private PersonalDataReader $personalData,
    ) {}

    /**
     * @return array{
     *     attendees: Collection<int, Attendee>,
     *     page: int,
     *     per_page: int,
     *     total: int,
     *     last_page: int
     * }
     */
    public function paginate(
        string $tenantId,
        string $eventId,
        ?string $search,
        ?string $checkinStatus,
        int $page = 1,
        ?string $registrationType = null,
        ?string $eventVenueId = null,
    ): array {
        $perPage = self::PER_PAGE;
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $needle = trim((string) $search);
        $status = $this->normalizeStatus($checkinStatus);

        $base = $this->baseQuery($tenantId, $eventId, $status, $registrationType, $eventVenueId);

        if ($needle === '') {
            $paginator = $base
                ->orderByDesc('registered_at')
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'attendees' => $paginator->getCollection(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => max(1, $paginator->lastPage()),
            ];
        }

        if ($this->applyExactContactFilter($base, $needle)) {
            $paginator = $base
                ->orderByDesc('registered_at')
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'attendees' => $paginator->getCollection(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => max(1, $paginator->lastPage()),
            ];
        }

        $filtered = $this->filterDecrypted(
            $base->orderByDesc('registered_at')->orderByDesc('id')->limit(self::SEARCH_SCAN_LIMIT)->get(),
            $needle,
        );

        $total = $filtered->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        return [
            'attendees' => $filtered->slice(($page - 1) * $perPage, $perPage)->values(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
        ];
    }

    /** @return Collection<int, Attendee> */
    public function forExport(
        string $tenantId,
        string $eventId,
        ?string $search,
        ?string $checkinStatus,
        ?string $eventVenueId = null,
        ?string $registrationType = null,
    ): Collection {
        $needle = trim((string) $search);
        $status = $this->normalizeStatus($checkinStatus);
        $base = $this->baseQuery($tenantId, $eventId, $status, $registrationType, $eventVenueId);

        if ($needle === '') {
            return $base
                ->orderByDesc('registered_at')
                ->orderByDesc('id')
                ->limit(self::EXPORT_LIMIT)
                ->get();
        }

        if ($this->applyExactContactFilter($base, $needle)) {
            return $base
                ->orderByDesc('registered_at')
                ->orderByDesc('id')
                ->limit(self::EXPORT_LIMIT)
                ->get();
        }

        return $this->filterDecrypted(
            $base->orderByDesc('registered_at')->orderByDesc('id')->limit(self::EXPORT_LIMIT)->get(),
            $needle,
        )->values();
    }

    /**
     * @return array{not_registered: int, registered: int, attended: int, not_attended: int}
     */
    public function statusCounts(
        string $tenantId,
        string $eventId,
        ?string $registrationType = null,
        ?string $eventVenueId = null,
    ): array {
        $counts = [
            'not_registered' => 0,
            'registered' => 0,
            'attended' => 0,
            'not_attended' => 0,
        ];

        $rows = $this->baseQuery($tenantId, $eventId, null, $registrationType, $eventVenueId)
            ->selectRaw("COALESCE(NULLIF(invite_status, ''), 'registered') as status_key, COUNT(*) as aggregate")
            ->groupByRaw("COALESCE(NULLIF(invite_status, ''), 'registered')")
            ->pluck('aggregate', 'status_key');

        foreach ($rows as $status => $count) {
            $key = (string) $status;
            if (array_key_exists($key, $counts)) {
                $counts[$key] = (int) $count;
            }
        }

        return $counts;
    }

    /** @return Builder<Attendee> */
    private function baseQuery(
        string $tenantId,
        string $eventId,
        ?string $status,
        ?string $registrationType = null,
        ?string $eventVenueId = null,
    ): Builder {
        $venueId = is_string($eventVenueId) && ctype_digit($eventVenueId) ? (int) $eventVenueId : null;

        $query = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('registration_status', '!=', 'cancelled')
            ->where('registration_status', '!=', 'anonymized')
            ->when($status !== null, fn (Builder $q) => $q->where('invite_status', $status))
            ->when($venueId !== null, fn (Builder $q) => $q->where('event_venue_id', $venueId));

        // Public tab: exclude attendees who completed registration through a private invite.
        if ($registrationType === 'public') {
            $privateEmailIndexes = $this->privateRegistrationEmailIndexes($eventId);

            if ($privateEmailIndexes !== []) {
                $query->whereNotIn('email_index', $privateEmailIndexes);
            }
        }

        // Private tab: only attendees who registered through a private invite.
        if ($registrationType === 'private') {
            $privateEmailIndexes = $this->privateRegistrationEmailIndexes($eventId);

            if ($privateEmailIndexes === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('email_index', $privateEmailIndexes);
            }
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private function privateRegistrationEmailIndexes(string $eventId): array
    {
        return EventRegistrationInvite::query()
            ->where('event_id', $eventId)
            ->whereNotNull('used_at')
            ->get(['email', 'email_index'])
            ->map(function ($invite): ?string {
                if (filled($invite->email_index)) {
                    return (string) $invite->email_index;
                }

                $email = is_string($invite->email) ? trim($invite->email) : '';

                return $email !== '' ? $this->indexes->email($email) : null;
            })
            ->filter(fn (?string $index): bool => is_string($index) && $index !== '')
            ->unique()
            ->values()
            ->all();
    }

    /** @param  Builder<Attendee>  $query */
    private function applyExactContactFilter(Builder $query, string $needle): bool
    {
        if (preg_match(self::EMAIL_PATTERN, $needle) === 1) {
            $query->where('email_index', $this->indexes->email($needle));

            return true;
        }

        if (preg_match(self::PHONE_PATTERN, $needle) === 1) {
            $query->where('phone_index', $this->indexes->phone($needle));

            return true;
        }

        return false;
    }

    /**
     * @param  Collection<int, Attendee>  $attendees
     * @return Collection<int, Attendee>
     */
    private function filterDecrypted(Collection $attendees, string $needle): Collection
    {
        $needleLower = mb_strtolower($needle);

        return $attendees->filter(function (Attendee $attendee) use ($needleLower): bool {
            if (str_contains(mb_strtolower((string) $attendee->id), $needleLower)) {
                return true;
            }

            $displayName = $this->personalData->attendeeDisplayName($attendee) ?? '';
            $email = $this->personalData->attendeeEmail($attendee) ?? '';
            $phone = $this->personalData->attendeePhone($attendee) ?? '';

            return str_contains(mb_strtolower($displayName), $needleLower)
                || str_contains(mb_strtolower($email), $needleLower)
                || str_contains(mb_strtolower($phone), $needleLower);
        })->values();
    }

    private function normalizeStatus(?string $status): ?string
    {
        $normalized = trim((string) $status);

        if ($normalized === '' || ! in_array($normalized, [
            'not_registered',
            'registered',
            'attended',
            'not_attended',
            'not_checked_in',
            'checked_in',
        ], true)) {
            return null;
        }

        return match ($normalized) {
            'not_checked_in' => 'registered',
            'checked_in' => 'attended',
            default => $normalized,
        };
    }
}
