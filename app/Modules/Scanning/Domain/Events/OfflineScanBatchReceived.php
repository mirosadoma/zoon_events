<?php

namespace App\Modules\Scanning\Domain\Events;

final readonly class OfflineScanBatchReceived
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $batchId,
        public int $submittedScanCount,
    ) {}
}
