<?php

namespace App\Modules\Scanning\Application\Actions;

use App\Modules\Scanning\Contracts\ScanDecisionEvaluator;
use App\Modules\Scanning\Domain\Events\OfflineScanBatchProcessed;
use App\Modules\Scanning\Domain\Events\OfflineScanBatchReceived;
use App\Modules\Scanning\Domain\Events\OfflineScanConflictFlagged;
use App\Modules\Scanning\Domain\Results\ScanDecision;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSummary;
use App\Modules\Scanning\Infrastructure\Persistence\Models\OfflineScanReconciliationBatch;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Shared\Contracts\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ReconcileOfflineScanBatchAction
{
    public function __construct(
        private ScanDecisionEvaluator $evaluator,
        private SubmitScanAction $submitScan,
        private GenerateOfflineAllowlistAction $allowlists,
        private Clock $clock,
    ) {}

    /**
     * @param  list<array{qr_payload:string,scanned_at:string,override?:bool,override_reason?:?string}>  $scans
     */
    public function execute(
        string $tenantId,
        string $eventId,
        string $deviceReference,
        array $scans,
        string $scannerId,
        string $scannerType = 'staff_phone',
        bool $actorCanOverride = false,
    ): OfflineScanReconciliationBatch {
        $allowlist = $this->allowlists->execute($tenantId, $eventId);

        $batch = OfflineScanReconciliationBatch::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'device_reference' => $deviceReference,
            'allowlist_issued_at' => $allowlist['issued_at'],
            'allowlist_expires_at' => $allowlist['expires_at'],
            'submitted_scan_count' => count($scans),
            'status' => 'received',
        ]);

        event(new OfflineScanBatchReceived($tenantId, $eventId, $batch->id, count($scans)));

        usort(
            $scans,
            fn (array $left, array $right): int => strtotime($left['scanned_at']) <=> strtotime($right['scanned_at']),
        );

        $accepted = 0;
        $duplicate = 0;
        $conflicts = 0;
        /** @var list<string> */
        $conflictedBatchIds = [];

        try {
            foreach ($scans as $scan) {
                $scannedAt = new \DateTimeImmutable($scan['scanned_at']);
                $context = new ScanContext(
                    tenantId: $tenantId,
                    eventId: $eventId,
                    scannerId: $scannerId,
                    scannerType: $scannerType,
                    qrPayload: $scan['qr_payload'],
                    override: (bool) ($scan['override'] ?? false),
                    overrideReason: $scan['override_reason'] ?? null,
                    actorCanOverride: $actorCanOverride,
                    offlineMode: true,
                    scannedAt: $scannedAt,
                );

                $evaluated = $this->evaluator->evaluate($context);
                [$decision, $relatedBatchIds] = $this->resolveOfflineConflict(
                    $tenantId,
                    $eventId,
                    $batch->id,
                    $context,
                    $evaluated,
                );

                if ($relatedBatchIds !== []) {
                    $conflicts++;
                    $conflictedBatchIds = [...$conflictedBatchIds, ...$relatedBatchIds];
                    event(new OfflineScanConflictFlagged(
                        $tenantId,
                        $eventId,
                        $batch->id,
                        $decision->credentialId,
                        $decision->reasonCode,
                    ));
                }

                // When conflict resolution overrides the evaluator decision, pass it as-is but
                // the caller (SubmitScanAction) will still re-evaluate under a credential row lock
                // via the forcedDecision=null path so that concurrent batches cannot double-accept.
                // We always pass null here so that SubmitScanAction applies its own lock + re-evaluation.
                $submission = $this->submitScan->execute($context, null);

                match ($submission->decision->result) {
                    'accepted', 'manual_override' => $accepted++,
                    'duplicate' => $duplicate++,
                    default => null,
                };
            }

        } catch (\Throwable $e) {
            $batch->forceFill(['status' => 'failed', 'processed_at' => $this->clock->now()])->save();
            throw $e;
        }

        foreach (array_unique($conflictedBatchIds) as $relatedBatchId) {
            if ($relatedBatchId !== $batch->id) {
                OfflineScanReconciliationBatch::query()->whereKey($relatedBatchId)->increment('conflict_count');
            }
        }

        $status = $conflicts > 0 ? 'processed_with_conflicts' : 'processed';
        $batch->forceFill([
            'accepted_count' => $accepted,
            'duplicate_count' => $duplicate,
            'conflict_count' => $conflicts,
            'status' => $status,
            'processed_at' => $this->clock->now(),
        ])->save();

        event(new OfflineScanBatchProcessed(
            $tenantId,
            $eventId,
            $batch->id,
            $status,
            $accepted,
            $duplicate,
            $conflicts,
        ));

        return $batch->refresh();
    }

    /**
     * @return array{0:ScanDecision,1:list<string>}
     */
    private function resolveOfflineConflict(
        string $tenantId,
        string $eventId,
        string $currentBatchId,
        ScanContext $context,
        ScanDecision $decision,
    ): array {
        if ($decision->credentialId === null) {
            return [$decision, []];
        }

        $existing = ScanEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('credential_id', $decision->credentialId)
            ->whereIn('result', ['accepted', 'manual_override'])
            ->orderBy('scanned_at')
            ->first();

        if ($existing === null) {
            return [$decision, []];
        }

        $incomingAt = $context->scannedAt ?? $this->clock->now();
        $priorBatchId = $this->findPriorBatchId($tenantId, $eventId, $currentBatchId);
        $related = array_values(array_filter([$priorBatchId, $currentBatchId]));

        if ($existing->offline_mode && $incomingAt < $existing->scanned_at) {
            $this->downgradeAcceptedScan($tenantId, $eventId, $existing);

            return [
                new ScanDecision(
                    'accepted',
                    'entry_granted',
                    $decision->credentialId,
                    $decision->attendeeId ?? $existing->attendee_id,
                ),
                $related,
            ];
        }

        if ($existing->offline_mode && $incomingAt > $existing->scanned_at) {
            return [
                new ScanDecision(
                    'duplicate',
                    'offline_conflict_resolution',
                    $decision->credentialId ?? $existing->credential_id,
                    $decision->attendeeId ?? $existing->attendee_id,
                ),
                $related,
            ];
        }

        return [$decision, []];
    }

    private function findPriorBatchId(string $tenantId, string $eventId, string $currentBatchId): ?string
    {
        return OfflineScanReconciliationBatch::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('id', '!=', $currentBatchId)
            ->whereIn('status', ['processed', 'processed_with_conflicts'])
            ->where('accepted_count', '>', 0)
            ->orderByDesc('processed_at')
            ->value('id');
    }

    private function downgradeAcceptedScan(string $tenantId, string $eventId, ScanEvent $scanEvent): void
    {
        DB::transaction(function () use ($tenantId, $eventId, $scanEvent): void {
            // Re-read under a lock to ensure another concurrent reconciliation has not already
            // downgraded this scan. If it has already been downgraded, skip the summary update.
            $locked = ScanEvent::query()
                ->where('id', $scanEvent->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! in_array($locked->result, ['accepted', 'manual_override'], true)) {
                return;
            }

            $locked->forceFill([
                'result' => 'duplicate',
                'reason' => 'offline_conflict_resolution',
            ])->save();

            $summary = EventCheckInSummary::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($summary !== null) {
                $summary->decrement('checked_in_count');
                $summary->increment('duplicate_count');
            }
        });
    }
}
