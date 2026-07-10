<?php

namespace App\Modules\AccessControl\Http\Controllers\Management;

use App\Modules\Events\Contracts\EventScope;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;

final readonly class AcsScopedEventGuard
{
    public function __construct(
        private TenantContextStore $contexts,
        private EventScope $events,
    ) {}

    public function assertExists(string $eventId): void
    {
        abort_unless(
            $this->events->exists($this->contexts->current()->tenant->id, $eventId),
            404,
        );
    }
}
