<?php

namespace App\Modules\Notifications\Domain\Events;

final readonly class NotificationTerminalStateReached
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $notificationId,
        public string $status,
        public string $channel,
        public ?string $reasonCode = null,
    ) {}
}
