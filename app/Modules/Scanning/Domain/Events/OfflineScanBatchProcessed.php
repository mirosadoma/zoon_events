<?php

namespace App\Modules\Scanning\Domain\Events;

final readonly class OfflineScanBatchProcessed
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $batchId,
        public string $status,
        public int $acceptedCount,
        public int $duplicateCount,
        public int $conflictCount,
    ) {}
}
