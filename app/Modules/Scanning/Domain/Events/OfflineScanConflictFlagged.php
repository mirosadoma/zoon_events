<?php

namespace App\Modules\Scanning\Domain\Events;

final readonly class OfflineScanConflictFlagged
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $batchId,
        public ?string $credentialId,
        public string $reasonCode,
    ) {}
}
