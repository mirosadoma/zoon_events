<?php

namespace App\Modules\Audit\Application\Listeners\Phase2;

use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Scanning\Domain\Events\OfflineScanBatchProcessed;
use App\Modules\Scanning\Domain\Events\OfflineScanBatchReceived;
use App\Modules\Scanning\Domain\Events\OfflineScanConflictFlagged;
use App\Modules\Scanning\Domain\Events\ScanAccepted;
use App\Modules\Scanning\Domain\Events\ScanDuplicate;
use App\Modules\Scanning\Domain\Events\ScanExpired;
use App\Modules\Scanning\Domain\Events\ScanManualOverride;
use App\Modules\Scanning\Domain\Events\ScanRejected;
use App\Modules\Scanning\Domain\Events\ScanRevoked;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;

final readonly class ScanAuditListener
{
    public function __construct(
        private AuditWriter $audit,
        private TenantContextStore $contexts,
    ) {}

    public function handleAccepted(ScanAccepted $event): void
    {
        $this->write('scan.accepted', $event->tenantId, $event->eventId, $event->scanEventId, $event->credentialId, $event->reasonCode);
    }

    public function handleManualOverride(ScanManualOverride $event): void
    {
        $this->write(
            'scan.manual_override',
            $event->tenantId,
            $event->eventId,
            $event->scanEventId,
            $event->credentialId,
            $event->reasonCode,
            ['override_reason' => $event->overrideReason],
        );
    }

    public function handleDuplicate(ScanDuplicate $event): void
    {
        $this->write('scan.duplicate', $event->tenantId, $event->eventId, $event->scanEventId, $event->credentialId, $event->reasonCode);
    }

    public function handleRevoked(ScanRevoked $event): void
    {
        $this->write('scan.revoked', $event->tenantId, $event->eventId, $event->scanEventId, $event->credentialId, $event->reasonCode);
    }

    public function handleExpired(ScanExpired $event): void
    {
        $this->write('scan.expired', $event->tenantId, $event->eventId, $event->scanEventId, $event->credentialId, $event->reasonCode);
    }

    public function handleRejected(ScanRejected $event): void
    {
        $this->write('scan.rejected', $event->tenantId, $event->eventId, $event->scanEventId, $event->credentialId, $event->reasonCode);
    }

    public function handleOfflineBatchReceived(OfflineScanBatchReceived $event): void
    {
        $this->writeBatch('offline_scan_batch.received', $event->tenantId, $event->eventId, $event->batchId, 'received', [
            'submitted_scan_count' => $event->submittedScanCount,
        ]);
    }

    public function handleOfflineBatchProcessed(OfflineScanBatchProcessed $event): void
    {
        $this->writeBatch('offline_scan_batch.processed', $event->tenantId, $event->eventId, $event->batchId, $event->status, [
            'accepted_count' => $event->acceptedCount,
            'duplicate_count' => $event->duplicateCount,
            'conflict_count' => $event->conflictCount,
        ]);
    }

    public function handleOfflineConflictFlagged(OfflineScanConflictFlagged $event): void
    {
        $this->writeBatch('offline_scan_batch.conflict_flagged', $event->tenantId, $event->eventId, $event->batchId, $event->reasonCode, [
            'credential_id' => $event->credentialId,
        ]);
    }

    /** @param array<string,mixed> $metadata */
    private function writeBatch(
        string $action,
        string $tenantId,
        string $eventId,
        string $batchId,
        string $reasonCode,
        array $metadata = [],
    ): void {
        $context = $this->contexts->currentOrNull();
        if ($context === null) {
            return;
        }

        $this->audit->write(
            'tenant',
            $tenantId,
            $action,
            'succeeded',
            $context->actor,
            $reasonCode,
            'offline_scan_reconciliation_batch',
            $batchId,
            array_merge(['event_id' => $eventId], $metadata),
        );
    }

    /** @param array<string,mixed> $metadata */
    private function write(
        string $action,
        string $tenantId,
        string $eventId,
        string $scanEventId,
        ?string $credentialId,
        string $reasonCode,
        array $metadata = [],
    ): void {
        $context = $this->contexts->currentOrNull();
        if ($context === null) {
            return;
        }

        $this->audit->write(
            'tenant',
            $tenantId,
            $action,
            'succeeded',
            $context->actor,
            $reasonCode,
            'scan_event',
            $scanEventId,
            array_merge(['event_id' => $eventId, 'credential_id' => $credentialId], $metadata),
        );
    }
}
