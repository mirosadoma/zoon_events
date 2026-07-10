<?php

namespace App\Modules\WalletPasses\Domain\ValueObjects;

final readonly class WalletPassGenerationRequest
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $attendeeId,
        public string $credentialId,
        public string $credentialStatus,
        public string $provider,
        public string $passSerialNumber,
        public string $locale,
        public ?string $credentialToken = null,
        public ?string $eventName = null,
        public ?string $eventDate = null,
        public ?string $eventLocation = null,
        public ?string $attendeeName = null,
        public ?string $ticketTypeLabel = null,
        public ?string $zoneTierLabel = null,
    ) {}
}
