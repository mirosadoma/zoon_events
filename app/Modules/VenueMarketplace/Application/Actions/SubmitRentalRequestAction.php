<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Exceptions\FoundationException;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\VenueMarketplace\Application\Services\RentalEventSnapshotResolver;
use App\Modules\VenueMarketplace\Domain\Events\RentalRequested;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\MarketplaceQuoteService;
use App\Modules\VenueMarketplace\Domain\ValueObjects\RentalWindow;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceCatalogPublication;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalAsset;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final readonly class SubmitRentalRequestAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private OrganizationEligibility $eligibility,
        private RentalEventSnapshotResolver $events,
        private MarketplaceQuoteService $quotes,
        private Dispatcher $dispatcher,
    ) {}

    /** @param list<string> $publicationPublicIds */
    public function execute(
        int $organizerTenantId,
        int $actorUserId,
        int $eventId,
        array $publicationPublicIds,
        string $requestedStartAt,
        string $requestedEndAt,
        string $quoteDigest,
        int $quoteVersion,
        string $idempotencyKey,
        string $correlationId,
    ): RentalRequest {
        $ids = array_values(array_unique($publicationPublicIds));
        sort($ids, SORT_STRING);
        if ($ids === []) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_NOT_FOUND);
        }
        $start = CarbonImmutable::parse($requestedStartAt)->utc();
        $end = CarbonImmutable::parse($requestedEndAt)->utc();
        if ($end <= $start) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_WINDOW_INVALID);
        }
        if (! $this->eligibility->check($organizerTenantId, OrganizationEligibility::REQUEST_RENTALS)->eligible) {
            throw new MarketplaceDomainException(Phase6Problem::ORGANIZATION_TYPE_NOT_ELIGIBLE);
        }

        $event = $this->events->resolve($organizerTenantId, $eventId);
        $payloadHash = hash('sha256', json_encode([
            $eventId, $ids, $start->toISOString(), $end->toISOString(), $quoteDigest, $quoteVersion,
        ], JSON_THROW_ON_ERROR));
        $keyHash = hash('sha256', $idempotencyKey);
        $existing = RentalRequest::query()->withoutGlobalScopes()
            ->where('organizer_tenant_id', $organizerTenantId)
            ->where('submitted_by_user_id', $actorUserId)
            ->where('idempotency_key_hash', $keyHash)
            ->with('assets')
            ->first();
        if ($existing !== null) {
            if (! hash_equals($existing->idempotency_payload_hash, $payloadHash)) {
                throw FoundationException::conflict(
                    'idempotency_conflict',
                    'The idempotency key was already used for a different rental request.',
                );
            }

            return $existing;
        }
        if (! $this->quotes->isCurrent($quoteDigest)) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_QUOTE_CHANGED);
        }

        return $this->transactions->run(
            function () use (
                $organizerTenantId, $actorUserId, $event, $ids, $start, $end,
                $quoteDigest, $quoteVersion, $keyHash, $payloadHash,
            ): RentalRequest {
                $publications = $this->publications($ids, true);
                $this->assertAvailability($publications, $start, $end);
                $ownerTenantId = (int) $publications->first()->tenant_id;
                if ($ownerTenantId === $organizerTenantId
                    || ! $this->eligibility->check($ownerTenantId, OrganizationEligibility::OWN_VENUES)->eligible) {
                    throw new MarketplaceDomainException(Phase6Problem::ORGANIZATION_TYPE_NOT_ELIGIBLE);
                }
                $window = new RentalWindow($start->toDateTimeImmutable(), $end->toDateTimeImmutable());
                $quote = $this->quotes->calculate($publications, $window);
                if ($quoteVersion !== MarketplaceQuoteService::VERSION
                    || ! hash_equals($quote['quote_digest'], $quoteDigest)) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_QUOTE_CHANGED);
                }
                $first = $publications->first();
                $rental = RentalRequest::query()->forceCreate([
                    'tenant_id' => $ownerTenantId,
                    'organizer_tenant_id' => $organizerTenantId,
                    'public_id' => (string) Str::ulid(),
                    'event_id' => $event['event_id'],
                    'venue_id' => $first->venue_id,
                    'venue_public_id' => $first->venue_public_id,
                    'venue_name_en' => $first->venue_name_en,
                    'venue_name_ar' => $first->venue_name_ar,
                    'status' => 'requested',
                    'dispute_status' => 'none',
                    'requested_start_at' => $start,
                    'requested_end_at' => $end,
                    'venue_timezone' => $first->timezone,
                    'quote_digest' => $quote['quote_digest'],
                    'quote_version' => $quote['quote_version'],
                    'event_snapshot' => $event['snapshot'],
                    'currency' => $quote['currency'],
                    'total_minor' => $quote['total_minor'],
                    'idempotency_key_hash' => $keyHash,
                    'idempotency_payload_hash' => $payloadHash,
                    'version' => 1,
                    'submitted_by_user_id' => $actorUserId,
                    'submitted_at' => now(),
                ]);
                foreach ($quote['lines'] as $index => $line) {
                    $publication = $publications->firstWhere('public_id', $line['publication_public_id']);
                    RentalAsset::query()->forceCreate([
                        'tenant_id' => $ownerTenantId,
                        'organizer_tenant_id' => $organizerTenantId,
                        'rental_request_id' => $rental->id,
                        'venue_asset_id' => $publication->venue_asset_id,
                        'asset_public_id' => $line['asset_public_id'],
                        'catalog_publication_id' => $publication->id,
                        'publication_public_id' => $publication->public_id,
                        'publication_version' => $line['publication_version'],
                        'asset_type' => $publication->asset_type,
                        'name_en' => $publication->asset_name_en,
                        'name_ar' => $publication->asset_name_ar,
                        'capabilities' => $publication->capabilities->pluck('capability_code')->values()->all(),
                        'selected_capabilities' => $line['selected_capabilities'],
                        'pricing_model' => $line['pricing_model'],
                        'unit_price_minor' => $line['unit_price_minor'],
                        'quantity' => $line['quantity'],
                        'billable_units' => $line['billable_units'],
                        'line_total_minor' => $line['line_total_minor'],
                        'currency' => $line['currency'],
                        'line_order' => $index + 1,
                        'created_at' => now(),
                    ]);
                }

                return $rental->load('assets');
            },
            function (RentalRequest $rental) use ($correlationId, $keyHash): void {
                $this->dispatcher->dispatch(new RentalRequested(
                    $rental->public_id,
                    (string) ($rental->event_snapshot['id'] ?? $rental->event_id),
                    (int) $rental->tenant_id,
                    (int) $rental->organizer_tenant_id,
                    (int) $rental->submitted_by_user_id,
                    $rental->status,
                    (int) $rental->total_minor,
                    $rental->currency,
                    $correlationId,
                    $keyHash,
                ));
            },
        );
    }

    /** @param list<string> $ids */
    private function publications(array $ids, bool $lock): Collection
    {
        $query = MarketplaceCatalogPublication::query()->withoutGlobalScopes()
            ->where('status', 'active')
            ->whereIn('public_id', $ids)
            ->with('capabilities')
            ->orderBy('public_id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $publications = $query->get();
        if ($publications->count() !== count($ids)) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_QUOTE_CHANGED);
        }
        if ($publications->pluck('venue_public_id')->unique()->count() !== 1) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_MIXED_VENUE);
        }
        if ($publications->pluck('currency')->unique()->count() !== 1) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_MIXED_CURRENCY);
        }

        return $publications;
    }

    private function assertAvailability(Collection $publications, CarbonImmutable $start, CarbonImmutable $end): void
    {
        foreach ($publications as $publication) {
            $available = collect($publication->availability_windows ?? [])->contains(
                fn (array $window): bool => CarbonImmutable::parse($window['starts_at'])->utc() <= $start
                    && CarbonImmutable::parse($window['ends_at'])->utc() >= $end,
            );
            if (! $available) {
                throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_UNAVAILABLE);
            }
        }
    }
}
