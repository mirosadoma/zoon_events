<?php

namespace App\Modules\Scanning\Domain\ValueObjects;

final readonly class ScanContext
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $scannerId,
        public string $scannerType,
        public string $qrPayload = '',
        public ?string $credentialId = null,
        public bool $override = false,
        public ?string $overrideReason = null,
        public bool $actorCanOverride = false,
        public bool $offlineMode = false,
        public ?\DateTimeInterface $scannedAt = null,
    ) {}
}
