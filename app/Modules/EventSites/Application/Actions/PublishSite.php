<?php

namespace App\Modules\EventSites\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\EventSites\Application\Support\PublicSiteUrl;
use App\Modules\EventSites\Application\Support\SiteBlockValidator;
use App\Modules\EventSites\Domain\SiteStatus;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSite;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSiteVersion;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class PublishSite
{
    public function __construct(
        private SiteBlockValidator $validator,
        private AuditWriter $audit,
    ) {}

    /**
     * @return array{
     *   version: int,
     *   published_at: string,
     *   blocks_hash: string,
     *   public_url: string,
     *   already_published: bool
     * }
     */
    public function execute(TenantContext $context, Event $event): array
    {
        return DB::transaction(function () use ($context, $event): array {
            $site = EventSite::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $draftBlocks = is_array($site->draft_blocks) ? $site->draft_blocks : [];
            $blockers = $this->validator->publishBlockers($draftBlocks, $event);

            if ($blockers !== []) {
                throw Phase1Problem::make('event_site.publish_blocked', [
                    'publish_blockers' => array_map(
                        static fn (string $key): string => __("event_sites.blockers.{$key}"),
                        $blockers,
                    ),
                ]);
            }

            $settings = is_array($site->settings) ? $site->settings : [];
            $publicUrl = PublicSiteUrl::relative($event, $settings, 'en');

            $newHash = EventSiteVersion::computeBlocksHash($draftBlocks);

            if ($site->live_version_id !== null) {
                $currentLive = EventSiteVersion::query()
                    ->where('id', $site->live_version_id)
                    ->first();

                if ($currentLive !== null && $currentLive->blocks_hash === $newHash) {
                    return [
                        'version' => $currentLive->version,
                        'published_at' => $currentLive->published_at?->toIso8601String() ?? now()->toIso8601String(),
                        'blocks_hash' => $currentLive->blocks_hash,
                        'public_url' => $publicUrl,
                        'already_published' => true,
                    ];
                }
            }

            if ($site->live_version_id !== null) {
                EventSiteVersion::query()
                    ->where('tenant_id', $context->tenant->id)
                    ->where('event_site_id', $site->id)
                    ->where('status', 'published')
                    ->update(['status' => 'superseded']);
            }

            $lastVersion = EventSiteVersion::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_site_id', $site->id)
                ->max('version') ?? 0;

            $version = EventSiteVersion::query()->create([
                'tenant_id' => $context->tenant->id,
                'event_id' => $event->id,
                'event_site_id' => $site->id,
                'version' => $lastVersion + 1,
                'status' => 'published',
                'blocks' => $draftBlocks,
                'blocks_hash' => $newHash,
                'block_count' => count($draftBlocks),
                'published_by_user_id' => $context->actor->id,
                'published_at' => now(),
            ]);

            $site->forceFill([
                'status' => SiteStatus::Published->value,
                'live_version_id' => $version->id,
                'published_at' => now(),
                'unpublished_at' => null,
            ])->save();

            $this->audit->writeTenant(
                'event_site.published',
                'succeeded',
                $context,
                targetType: 'event_site',
                targetId: (string) $site->id,
                metadata: ['version' => $version->version],
            );

            return [
                'version' => $version->version,
                'published_at' => $version->published_at->toIso8601String(),
                'blocks_hash' => $version->blocks_hash,
                'public_url' => $publicUrl,
                'already_published' => false,
            ];
        });
    }
}
