<?php

namespace App\Modules\Scanning\Application\Actions;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Scanning\Application\Results\ScanSubmission;
use App\Modules\Scanning\Contracts\ScanDecisionEvaluator;
use App\Modules\Scanning\Domain\Events\ScanAccepted;
use App\Modules\Scanning\Domain\Events\ScanDuplicate;
use App\Modules\Scanning\Domain\Events\ScanExpired;
use App\Modules\Scanning\Domain\Events\ScanManualOverride;
use App\Modules\Scanning\Domain\Events\ScanRejected;
use App\Modules\Scanning\Domain\Events\ScanRevoked;
use App\Modules\Scanning\Domain\Results\ScanDecision;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSummary;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Support\Facades\DB;

final readonly class SubmitScanAction
{
    public function __construct(
        private ScanDecisionEvaluator $evaluator,
        private AuditedTransaction $audited,
        private PersonalDataCipher $cipher,
    ) {}

    public function execute(ScanContext $context, ?ScanDecision $forcedDecision = null): ScanSubmission
    {
        return $this->audited->run(
            function () use ($context, $forcedDecision): ScanSubmission {
                $decision = $forcedDecision ?? $this->evaluator->evaluate($context);

                if ($forcedDecision === null
                    && ! in_array($decision->result, ['rejected', 'revoked', 'expired'], true)
                    && $decision->credentialId !== null) {
                    Credential::query()
                        ->where('tenant_id', $context->tenantId)
                        ->where('event_id', $context->eventId)
                        ->where('id', $decision->credentialId)
                        ->lockForUpdate()
                        ->first();

                    $decision = $this->evaluator->evaluate($context);
                }

                return $this->persistWithinTransaction($context, $decision);
            },
            fn (ScanSubmission $submission): mixed => $this->dispatchScanEvent($context, $submission),
        );
    }

    private function persistWithinTransaction(ScanContext $context, ScanDecision $decision): ScanSubmission
    {
        $scannedAt = $context->scannedAt ?? now();
        $display = $this->resolveDisplayFields($context, $decision);
        $displayNameCiphertext = null;

        if ($display['attendee_display_name'] !== null) {
            $displayNameCiphertext = $this->cipher->encrypt(
                $display['attendee_display_name'],
                "{$context->tenantId}:{$context->eventId}:scan-event",
            )['ciphertext'];
        }

        $scanEvent = ScanEvent::query()->create([
            'tenant_id' => $context->tenantId,
            'event_id' => $context->eventId,
            'attendee_id' => $decision->attendeeId,
            'credential_id' => $decision->credentialId,
            'scanner_type' => $context->scannerType,
            'scanner_id' => $context->scannerId,
            'direction' => 'in',
            'result' => $decision->result,
            'reason' => $decision->reasonCode,
            'attendee_display_name_ciphertext' => $displayNameCiphertext,
            'offline_mode' => $context->offlineMode,
            'scanned_at' => $scannedAt,
            'synced_at' => $context->offlineMode ? now() : null,
        ]);

        if (in_array($decision->result, ['accepted', 'manual_override'], true) && $decision->attendeeId !== null) {
            $attendee = Attendee::query()
                ->where('tenant_id', $context->tenantId)
                ->where('event_id', $context->eventId)
                ->findOrFail($decision->attendeeId);

            $attendee->forceFill([
                'checkin_status' => 'checked_in',
                'first_checked_in_at' => $attendee->first_checked_in_at ?? $scannedAt,
                'last_scan_event_id' => $scanEvent->id,
            ])->save();
        }

        $this->updateSummary($context, $decision->result, $scannedAt);

        return new ScanSubmission(
            $scanEvent->id,
            $decision,
            $display['attendee_display_name'],
            $display['ticket_type_label'],
        );
    }

    private function updateSummary(ScanContext $context, string $result, \DateTimeInterface $scannedAt): void
    {
        $summary = EventCheckInSummary::query()
            ->where('tenant_id', $context->tenantId)
            ->where('event_id', $context->eventId)
            ->lockForUpdate()
            ->first();

        if ($summary === null) {
            $registeredCount = DB::table('attendees')
                ->where('tenant_id', $context->tenantId)
                ->where('event_id', $context->eventId)
                ->count();

            EventCheckInSummary::query()->insertOrIgnore([
                'tenant_id' => $context->tenantId,
                'event_id' => $context->eventId,
                'registered_count' => $registeredCount,
                'checked_in_count' => 0,
                'rejected_count' => 0,
                'duplicate_count' => 0,
                'last_scan_at' => $scannedAt,
            ]);

            $summary = EventCheckInSummary::query()
                ->where('tenant_id', $context->tenantId)
                ->where('event_id', $context->eventId)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $increments = match ($result) {
            'accepted', 'manual_override' => ['checked_in_count' => 1],
            'rejected' => ['rejected_count' => 1],
            'duplicate', 'revoked', 'expired' => ['duplicate_count' => 1],
            default => [],
        };

        foreach ($increments as $column => $amount) {
            $summary->increment($column, $amount);
        }

        $summary->forceFill(['last_scan_at' => $scannedAt])->save();
    }

    /** @return array{attendee_display_name:?string,ticket_type_label:?string} */
    private function resolveDisplayFields(ScanContext $context, ScanDecision $decision): array
    {
        if (! in_array($decision->result, ['accepted', 'manual_override'], true)
            || $decision->credentialId === null
            || $decision->attendeeId === null) {
            return ['attendee_display_name' => null, 'ticket_type_label' => null];
        }

        try {
            $credential = Credential::query()
                ->where('tenant_id', $context->tenantId)
                ->where('event_id', $context->eventId)
                ->findOrFail($decision->credentialId);
            $attendee = Attendee::query()
                ->where('tenant_id', $context->tenantId)
                ->where('event_id', $context->eventId)
                ->findOrFail($decision->attendeeId);
            $ticket = TicketType::query()
                ->where('tenant_id', $context->tenantId)
                ->where('event_id', $context->eventId)
                ->findOrFail($credential->ticket_type_id);

            $name = trim($this->cipher->decrypt(
                ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->first_name_ciphertext],
                "{$context->tenantId}:{$context->eventId}:attendee",
            ).' '.$this->cipher->decrypt(
                ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->last_name_ciphertext],
                "{$context->tenantId}:{$context->eventId}:attendee",
            ));

            return [
                'attendee_display_name' => $name,
                'ticket_type_label' => $ticket->name_en,
            ];
        } catch (\Throwable) {
            return ['attendee_display_name' => null, 'ticket_type_label' => null];
        }
    }

    private function dispatchScanEvent(ScanContext $context, ScanSubmission $submission): void
    {
        $event = match ($submission->decision->result) {
            'accepted' => new ScanAccepted(
                $context->tenantId,
                $context->eventId,
                $submission->scanEventId,
                $submission->decision->credentialId,
                $submission->decision->reasonCode,
            ),
            'manual_override' => new ScanManualOverride(
                $context->tenantId,
                $context->eventId,
                $submission->scanEventId,
                $submission->decision->credentialId,
                $submission->decision->reasonCode,
                $context->overrideReason,
            ),
            'duplicate' => new ScanDuplicate(
                $context->tenantId,
                $context->eventId,
                $submission->scanEventId,
                $submission->decision->credentialId,
                $submission->decision->reasonCode,
            ),
            'revoked' => new ScanRevoked(
                $context->tenantId,
                $context->eventId,
                $submission->scanEventId,
                $submission->decision->credentialId,
                $submission->decision->reasonCode,
            ),
            'expired' => new ScanExpired(
                $context->tenantId,
                $context->eventId,
                $submission->scanEventId,
                $submission->decision->credentialId,
                $submission->decision->reasonCode,
            ),
            default => new ScanRejected(
                $context->tenantId,
                $context->eventId,
                $submission->scanEventId,
                $submission->decision->credentialId,
                $submission->decision->reasonCode,
            ),
        };

        event($event);
    }
}
