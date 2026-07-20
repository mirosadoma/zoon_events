<?php

namespace App\Modules\VenueMarketplace\Application\Services;

use Illuminate\Contracts\Cache\Repository;

final readonly class MarketplaceCatalogCache
{
    public function __construct(private Repository $cache) {}

    /** @param array<string, mixed> $filters */
    public function remember(
        int $actorTenantId,
        string $locale,
        array $filters,
        ?string $cursor,
        callable $loader,
    ): mixed {
        if (! config('marketplace.catalog.cache_enabled', false)) {
            return $loader();
        }

        ksort($filters);
        $generation = (int) $this->cache->get('marketplace:catalog:generation', 1);
        $digest = hash('sha256', json_encode([
            'generation' => $generation,
            'actor_tenant_id' => $actorTenantId,
            'locale' => $locale,
            'filters' => $filters,
            'cursor' => $cursor,
        ], JSON_THROW_ON_ERROR));

        return $this->cache->remember(
            "marketplace:catalog:{$digest}",
            (int) config('marketplace.catalog.cache_ttl_seconds', 300),
            $loader,
        );
    }

    public function invalidate(): void
    {
        $this->cache->increment('marketplace:catalog:generation');
    }
}
