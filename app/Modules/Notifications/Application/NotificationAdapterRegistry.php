<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Contracts\NotificationAdapter;
use InvalidArgumentException;

final readonly class NotificationAdapterRegistry
{
    /** @param array<string,NotificationAdapter> $adapters */
    public function __construct(private array $adapters) {}

    public function get(string $key): NotificationAdapter
    {
        return $this->adapters[$key] ?? throw new InvalidArgumentException('Notification adapter is unavailable.');
    }
}
