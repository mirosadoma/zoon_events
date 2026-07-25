<?php

namespace App\Modules\AdminConsole\Application\Queries;

use App\Modules\AdminConsole\Application\PersonalDataReader;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
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
    ): array {
        $perPage = self::PER_PAGE;
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $needle = trim((string) $search);
        $status = $this->normalizeStatus($checkinStatus);

        $base = $this->baseQuery($tenantId, $eventId, $status, $registrationType);

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
    ): Collection {
        $needle = trim((string) $search);
        $status = $this->normalizeStatus($checkinStatus);
        $base = $this->baseQuery($tenantId, $eventId, $status);

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

    /** @return Builder<Attendee> */
    private function baseQuery(string $tenantId, string $eventId, ?string $status, ?string $registrationType = null): Builder
    {
        $query = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->when($status !== null, fn (Builder $q) => $q->where('invite_status', $status));

        // Filter by registration type: public = no invite used, private = invite used
        if ($registrationType === 'public') {
            $query->where(function (Builder $q) use ($eventId): void {
                $q->whereNull('origin')
                    ->orWhere('origin', '!=', 'invite')
                    ->orWhereNotExists(function ($subquery) use ($eventId): void {
                        $subquery->selectRaw('1')
                            ->from('event_registration_invites')
                            ->whereColumn('event_registration_invites.email', 'attendees.email_index')
                            ->where('event_registration_invites.event_id', $eventId)
                            ->whereIn('event_registration_invites.invite_status', ['registered', 'attended', 'not_attended']);
                    });
            });
        }

        return $query;
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
