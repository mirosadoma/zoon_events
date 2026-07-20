<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Models\User;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Authorization\Policies\Phase3\Phase3Policy;
use App\Modules\BadgePrinting\Contracts\PrinterAdapter;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobReprinted;
use App\Modules\BadgePrinting\Domain\Results\PrintResult;
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
        string $badgePrintJobId,
        string $reason,
    ): BadgePrintJob {
        $tenantId = $tenantContext->tenant->id;

        $targetJob = BadgePrintJob::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($badgePrintJobId);

        if ($targetJob === null) {
            throw Phase3Problem::make('badge_no_prior_print_job');
        }

        $attendeeId = (string) $targetJob->attendee_id;

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

        $settings = EventCheckInSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        $credentialId = (string) $targetJob->credential_id;

        if ($settings?->reprint_revokes_old_qr) {
            $reissued = $this->reissue->execute($tenantContext, $eventId, $credentialId, $reason);
            $credentialId = $reissued->id;
        }

        $template = BadgeTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('id', $targetJob->badge_template_id)
            ->first();

        if ($template === null) {
            $template = BadgeTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first();
        }

        if ($template === null) {
            throw Phase3Problem::make('badge_template_not_active');
        }

        $payload = $this->renderer->execute($tenantId, $eventId, $attendeeId, $credentialId, $template);

        return $this->transaction->run(
            mutation: function () use ($targetJob, $credentialId, $template, $payload, $actor, $reason): BadgePrintJob {
                $targetJob->forceFill([
                    'credential_id' => $credentialId,
                    'badge_template_id' => $template->id,
                    'printed_by_user_id' => $actor->id,
                    'status' => 'queued',
                    'failure_reason' => null,
                    'is_reprint' => true,
                    'reprint_reason' => $reason,
                    'printed_at' => null,
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

                $targetJob->forceFill([
                    'status' => $result->status === 'printed' ? 'printed' : 'failed',
                    'failure_reason' => $result->status !== 'printed' ? ($result->reasonCode ?? 'unknown') : null,
                    'printed_at' => $result->status === 'printed' ? CarbonImmutable::now() : null,
                ])->save();

                return $targetJob->refresh();
            },
            audit: function (BadgePrintJob $job) use ($tenantId, $eventId, $reason): void {
                event(new BadgePrintJobReprinted(
                    $tenantId,
                    $eventId,
                    (string) $job->id,
                    (string) $job->attendee_id,
                    (string) ($job->original_print_job_id ?? $job->id),
                    $reason,
                ));
            },
        );
    }
}
