<?php

namespace App\Modules\Events\Application\Contracts;

final readonly class MarketplaceEventReadResult
{
    private function __construct(
        public ?MarketplaceEventSnapshot $event,
        public ?string $reason,
    ) {}

    public static function found(MarketplaceEventSnapshot $event): self
    {
        return new self($event, null);
    }

    public static function denied(string $reason): self
    {
        return new self(null, $reason);
    }

    public function foundEvent(): bool
    {
        return $this->event !== null;
    }
}
