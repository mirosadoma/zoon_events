<?php

namespace App\Modules\Notifications\Domain;

final readonly class NotificationResult
{
    public function __construct(
        public NotificationStatus $status,
        public ?string $providerMessageId = null,
        public ?string $reasonCode = null,
    ) {}
}
