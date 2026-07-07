<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Models\User;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Authorization\Policies\Phase3\Phase3Policy;
use App\Modules\BadgePrinting\Contracts\PrinterAdapter;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobReprinted;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Credentials\Application\Actions\ReissueCredential;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSetting;
use App\Modules\Shared\Http\Problems\Phase3Problem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Carbon\CarbonImmutable;

final readonly class ReprintBadgeAction
{
    public function __construct(
        private Phase3Policy $policy,
        private ReissueCredential $reissue,
        private AuditWriter $audit,
        private RenderBadgePrintPayloadAction $renderer,
        private PrinterAdapter $printer,
        private AuditedTransaction $transaction,
    ) {}

    public function execute(
        User $actor,
        TenantContext $tenantContext,
        string $eventId,
        string $attendeeId,
        string $reason,
    ): BadgePrintJob {
        $tenantId = $tenantContext->tenant->id;

        if (! $this->policy->allows($actor, 'reprintBadge')) {
            $this->audit->write(
                'tenant', $tenantId, 'badge_print.reprint_blocked', 'blocked',
                actor: $actor,
                reasonCode: 'badge_reprint_not_permitted',
                targetType: 'attendee', targetId: $attendeeId,
            );
            throw Phase3Problem::make('badge_reprint_not_permitted');
        }

        if (trim($reason) === '') {
            $this->audit->write(
                'tenant', $tenantId, 'badge_print.reprint_blocked', 'blocked',
                actor: $actor,
                reasonCode: 'badge_reprint_reason_required',
                targetType: 'attendee', targetId: $attendeeId,
            );
            throw Phase3Problem::make('badge_reprint_reason_required');
        }

        $priorJob = BadgePrintJob::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('attendee_id', $attendeeId)
            ->orderByDesc('created_at')
            ->first();

        if ($priorJob === null) {
            $this->audit->write(
                'tenant', $tenantId, 'badge_print.reprint_blocked', 'blocked',
                actor: $actor,
                reasonCode: 'badge_no_prior_print_job',
                targetType: 'attendee', targetId: $attendeeId,
            );
            throw Phase3Problem::make('badge_no_prior_print_job');
        }

        $settings = EventCheckInSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        $credentialId = $priorJob->credential_id;

        if ($settings?->reprint_revokes_old_qr) {
            $reissued = $this->reissue->execute($tenantContext, $eventId, $credentialId, $reason);
            $credentialId = $reissued->credentialId;
        }

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
            mutation: function () use ($tenantId, $eventId, $attendeeId, $credentialId, $template, $payload, $priorJob, $actor, $reason): BadgePrintJob {
                $job = BadgePrintJob::create([
                    'tenant_id'              => $tenantId,
                    'event_id'               => $eventId,
                    'attendee_id'            => $attendeeId,
                    'credential_id'          => $credentialId,
                    'badge_template_id'      => $template->id,
                    'printed_by_user_id'     => $actor->id,
                    'status'                 => 'queued',
                    'is_reprint'             => true,
                    'reprint_reason'         => $reason,
                    'original_print_job_id'  => $priorJob->id,
                ]);

                $result = $this->printer->print($payload);

                $job->forceFill([
                    'status'         => $result->status === 'printed' ? 'printed' : 'failed',
                    'failure_reason' => $result->status !== 'printed' ? ($result->reasonCode ?? 'unknown') : null,
                    'printed_at'     => $result->status === 'printed' ? CarbonImmutable::now() : null,
                ])->save();

                return $job;
            },
            audit: function (BadgePrintJob $job) use ($tenantId, $eventId, $priorJob, $reason): void {
                event(new BadgePrintJobReprinted(
                    $tenantId, $eventId, $job->id, $job->attendee_id, $priorJob->id, $reason
                ));
            },
        );
    }
}
