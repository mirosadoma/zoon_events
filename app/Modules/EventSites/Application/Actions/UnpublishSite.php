<?php

namespace App\Modules\EventSites\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\EventSites\Domain\SiteStatus;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSite;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class UnpublishSite
{
    public function __construct(
        private AuditWriter $audit,
    ) {}

    /**
     * @return array{status: string, unpublished_at: string}
     */
    public function execute(TenantContext $context, Event $event): array
    {
        return DB::transaction(function () use ($context, $event): array {
            $site = EventSite::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($site->status === SiteStatus::Unpublished->value) {
                return [
                    'status' => $site->status,
                    'unpublished_at' => $site->unpublished_at?->toIso8601String() ?? now()->toIso8601String(),
                ];
            }

            $site->forceFill([
                'status' => SiteStatus::Unpublished->value,
                'unpublished_at' => now(),
            ])->save();

            $this->audit->writeTenant(
                'event_site.unpublished',
                'succeeded',
                $context,
                targetType: 'event_site',
                targetId: (string) $site->id,
            );

            return [
                'status' => $site->status,
                'unpublished_at' => $site->unpublished_at->toIso8601String(),
            ];
        });
    }
}
