<?php

namespace Tests\Support;

use App\Models\User;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\VenueMarketplaceFactory;
use Illuminate\Support\Str;

trait CreatesMarketplaceFixture
{
    use BuildsTenantFixtures;

    private const MARKETPLACE_FIXTURE_CLOCK = '2026-07-14T12:00:00+00:00';

    /** @var list<string> */
    private const MARKETPLACE_FIXTURE_ULIDS = [
        '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        '01ARZ3NDEKTSV4RRFFQ69G5FAW',
        '01ARZ3NDEKTSV4RRFFQ69G5FAX',
    ];

    /**
     * @return array{amount_minor:int,currency:string}
     */
    protected function marketplaceMoney(int $amountMinor = 10_000, string $currency = 'SAR'): array
    {
        return [
            'amount_minor' => $amountMinor,
            'currency' => $currency,
        ];
    }

    protected function marketplaceClock(?string $instant = null): CarbonImmutable
    {
        return CarbonImmutable::parse($instant ?? self::MARKETPLACE_FIXTURE_CLOCK);
    }

    protected function freezeMarketplaceClock(?string $instant = null): CarbonImmutable
    {
        $clock = $this->marketplaceClock($instant);
        CarbonImmutable::setTestNow($clock);

        return $clock;
    }

    protected function marketplaceUlid(int $sequence = 0): string
    {
        return self::MARKETPLACE_FIXTURE_ULIDS[$sequence % count(self::MARKETPLACE_FIXTURE_ULIDS)];
    }

    /**
     * @return array{user:User,tenant:Tenant}
     */
    protected function createMarketplaceTenantMember(array $userAttributes = [], array $tenantAttributes = [], array $membershipAttributes = []): array
    {
        $fixture = $this->createTenantMember($userAttributes, $tenantAttributes, $membershipAttributes);

        return [
            'user' => $fixture['user'],
            'tenant' => $fixture['tenant'],
        ];
    }

    protected function createMarketplaceEvent(
        Tenant $tenant,
        User $actor,
        array $attributes = [],
    ): Event {
        return Event::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'slug' => 'marketplace-'.Str::lower($this->marketplaceUlid()),
            'name_en' => 'Marketplace Event',
            'name_ar' => 'فعالية السوق',
            'tier' => 'public',
            'status' => 'published',
            'timezone' => 'Asia/Riyadh',
            'start_at' => '2027-01-10 12:00:00',
            'end_at' => '2027-01-10 18:00:00',
            'registration_opens_at' => '2026-01-01 00:00:00',
            'registration_closes_at' => '2027-01-10 11:00:00',
            'capacity' => 100,
            'created_by_user_id' => $actor->id,
            'published_by_user_id' => $actor->id,
            'published_at' => $this->marketplaceClock(),
        ], $attributes));
    }

    /**
     * @param  array{user:User,tenant:Tenant,membership:mixed}  $fixture
     * @return array{venue:mixed,assets:list<mixed>}
     */
    protected function createPublishedMarketplaceInventory(array $fixture, string $key = 'fixture'): array
    {
        app(TenantContextStore::class)->bind(
            $fixture['tenant'],
            $fixture['membership'],
            $fixture['user'],
        );

        return app(VenueMarketplaceFactory::class)->createPublishedInventory(
            (int) $fixture['tenant']->id,
            (int) $fixture['user']->id,
            $key,
        );
    }

    /**
     * @param  array{user:User,tenant:Tenant}  $organizer
     * @param  list<string>  $publicationPublicIds
     */
    protected function createSubmittedMarketplaceRental(
        array $organizer,
        Event $event,
        array $publicationPublicIds,
        string $key = 'fixture-rental',
    ): mixed {
        app(TenantContextStore::class)->clear();

        return app(VenueMarketplaceFactory::class)->createSubmittedRental(
            (int) $organizer['tenant']->id,
            (int) $organizer['user']->id,
            (int) $event->id,
            $publicationPublicIds,
            key: $key,
        );
    }
}
