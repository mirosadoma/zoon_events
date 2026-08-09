<?php

namespace App\Modules\EventSites\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSite;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSiteVersion;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class RestoreSiteVersion
{
    public function __construct(
        private AuditWriter $audit,
    ) {}

    /**
     * @return array{
     *   draft_revision: int,
     *   draft_blocks: array<int, array<string, mixed>>,
     *   restored_from_version: int
     * }
     */
    public function execute(TenantContext $context, Event $event, int $versionId): array
    {
        return DB::transaction(function () use ($context, $event, $versionId): array {
            $site = EventSite::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $version = EventSiteVersion::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_site_id', $site->id)
                ->where('id', $versionId)
                ->firstOrFail();

            $blocks = is_array($version->blocks) ? $version->blocks : [];

            $site->forceFill([
                'draft_blocks' => $blocks,
                'draft_updated_by_user_id' => $context->actor->id,
                'draft_revision' => $site->draft_revision + 1,
            ])->save();

            $this->audit->writeTenant(
                'event_site.version_restored',
                'succeeded',
                $context,
                targetType: 'event_site',
                targetId: (string) $site->id,
                metadata: ['restored_version' => $version->version],
            );

            return [
                'draft_revision' => $site->draft_revision,
                'draft_blocks' => $site->draft_blocks,
                'restored_from_version' => $version->version,
            ];
        });
    }
}
