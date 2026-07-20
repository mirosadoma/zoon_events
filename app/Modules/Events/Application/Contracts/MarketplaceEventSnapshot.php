<?php

namespace App\Modules\Events\Application\Contracts;

final readonly class MarketplaceEventSnapshot
{
    public function __construct(
        public int $tenantId,
        public int $eventId,
        public string $eventPublicId,
        public string $nameEn,
        public string $nameAr,
        public string $status,
        public MarketplaceEventWindow $window,
        public bool $creatorEligible,
    ) {}
}
