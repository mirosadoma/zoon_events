<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\BadgePrinting\Contracts\PrinterAdapter;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobCreated;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobFailed;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobPrinted;
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
                $job = BadgePrintJob::create([
                    'tenant_id'          => $tenantId,
                    'event_id'           => $eventId,
                    'attendee_id'        => $attendeeId,
                    'credential_id'      => $credentialId,
                    'badge_template_id'  => $template->id,
                    'kiosk_id'           => $kioskId,
                    'printed_by_user_id' => $printedByUserId,
                    'status'             => 'queued',
                    'is_reprint'         => false,
                ]);

                $result = $this->printer->print($payload);

                $job->forceFill([
                    'status'         => $result->status === 'printed' ? 'printed' : 'failed',
                    'failure_reason' => $result->status !== 'printed' ? ($result->reasonCode ?? 'unknown') : null,
                    'printed_at'     => $result->status === 'printed' ? CarbonImmutable::now() : null,
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
