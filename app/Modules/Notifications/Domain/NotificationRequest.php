<?php

namespace App\Modules\Notifications\Domain;

final readonly class NotificationRequest
{
    /** @param list<NotificationEmbeddedImage> $embeddedImages */
    public function __construct(
        public string $tenantId,
        public string $notificationId,
        public NotificationChannel $channel,
        public string $destination,
        public string $senderReference,
        public string $subject,
        public string $body,
        public string $locale,
        public string $correlationId,
        public string $idempotencyKey,
        public array $embeddedImages = [],
    ) {}
}
