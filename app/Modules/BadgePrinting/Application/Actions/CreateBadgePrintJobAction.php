<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\BadgePrinting\Contracts\PrinterAdapter;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobCreated;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobFailed;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobPrinted;
use App\Modules\BadgePrinting\Domain\Results\PrintResult;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Shared\Http\Problems\Phase3Problem;
use Carbon\CarbonImmutable;

final readonly class CreateBadgePrintJobAction
{
    public function __construct(
        private RenderBadgePrintPayloadAction $renderer,
        private PrinterAdapter $printer,
        private AuditedTransaction $transaction,
    ) {}

    public function execute(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $credentialId,
        ?string $kioskId,
        ?string $printedByUserId,
    ): BadgePrintJob {
        $template = BadgeTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'active')
            ->first();

        if ($template === null) {
            throw Phase3Problem::make('badge_template_not_active');
        }

        $payload = $this->renderer->execute($tenantId, $eventId, $attendeeId, $credentialId, $template);

        return $this->transaction->run(
            mutation: function () use ($tenantId, $eventId, $attendeeId, $credentialId, $template, $payload, $kioskId, $printedByUserId): BadgePrintJob {
                $siblings = BadgePrintJob::query()
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $eventId)
                    ->where('attendee_id', $attendeeId)
                    ->orderByDesc('id')
                    ->get();

                $existing = $siblings->first();
                $previousCount = 0;

                if ($siblings->isNotEmpty()) {
                    $previousCount = (int) $siblings->sum(function (BadgePrintJob $job): int {
                        if ($job->status !== 'printed') {
                            return 0;
                        }

                        $count = (int) ($job->print_count ?? 0);

                        return $count > 0 ? $count : 1;
                    });

                    if ($siblings->count() > 1) {
                        BadgePrintJob::query()
                            ->whereIn('id', $siblings->skip(1)->pluck('id')->all())
                            ->delete();
                    }
                }

                $isReprint = $previousCount > 0;

                $job = $existing ?? new BadgePrintJob;
                $job->forceFill([
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'attendee_id' => $attendeeId,
                    'credential_id' => $credentialId,
                    'badge_template_id' => $template->id,
                    'kiosk_id' => $kioskId ?? $existing?->kiosk_id,
                    'printed_by_user_id' => $printedByUserId ?? $existing?->printed_by_user_id,
                    'status' => 'queued',
                    'failure_reason' => null,
                    'is_reprint' => $isReprint,
                    'print_count' => max(0, $previousCount),
                ])->save();

                try {
                    $result = $this->printer->print($payload);
                } catch (\Throwable) {
                    $result = new PrintResult(
                        status: 'failed',
                        reasonCode: 'printer_error',
                        confirmationReference: null,
                    );
                }

                $printed = $result->status === 'printed';
                $job->forceFill([
                    'status' => $printed ? 'printed' : 'failed',
                    'failure_reason' => $printed ? null : ($result->reasonCode ?? 'unknown'),
                    'printed_at' => $printed ? CarbonImmutable::now() : $job->printed_at,
                    'print_count' => $printed ? $previousCount + 1 : $previousCount,
                    'is_reprint' => $isReprint,
                ])->save();

                return $job;
            },
            audit: function (BadgePrintJob $job) use ($tenantId, $eventId): void {
                event(new BadgePrintJobCreated($tenantId, $eventId, $job->id, $job->attendee_id, null));

                if ($job->status === 'printed') {
                    event(new BadgePrintJobPrinted($tenantId, $eventId, $job->id, $job->attendee_id, null));
                } else {
                    event(new BadgePrintJobFailed($tenantId, $eventId, $job->id, $job->attendee_id, $job->failure_reason));
                }
            },
        );
    }
}
