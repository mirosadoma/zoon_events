<?php

namespace App\Modules\EventSites\Application\Queries;

use App\Modules\Events\Application\Support\PublicRegistrationUrlBuilder;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\EventSites\Application\Support\PublicSiteUrl;
use App\Modules\EventSites\Application\Support\SiteBlockDefaults;
use App\Modules\EventSites\Application\Support\SiteBlockValidator;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSite;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSiteVersion;
use App\Modules\Tenancy\Domain\Context\TenantContext;

final readonly class GetOrganizerSite
{
    public function __construct(
        private SiteBlockDefaults $defaults,
        private SiteBlockValidator $validator,
        private PublicRegistrationUrlBuilder $registrationUrls,
    ) {}

    /**
     * @return array{
     *   status: string,
     *   page_mode: string,
     *   draft_revision: int,
     *   draft_blocks: array<int, array<string, mixed>>,
     *   settings: array<string, mixed>,
     *   live_version: array<string, mixed>|null,
     *   public_url: string,
     *   publish_blockers: list<string>
     * }
     */
    public function execute(TenantContext $context, Event $event): array
    {
        $site = EventSite::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->first();

        if ($site === null) {
            $site = $this->createDraft($context, $event);
        } else {
            $site = $this->ensureChromeBlocks($site, $event);
        }

        $liveVersion = null;
        if ($site->live_version_id !== null) {
            $version = EventSiteVersion::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $event->id)
                ->where('id', $site->live_version_id)
                ->first();

            if ($version !== null) {
                $liveVersion = [
                    'id' => $version->id,
                    'version' => $version->version,
                    'published_at' => $version->published_at?->toIso8601String(),
                    'blocks_hash' => $version->blocks_hash,
                    'block_count' => $version->block_count,
                ];
            }
        }

        $draftBlocks = is_array($site->draft_blocks) ? $site->draft_blocks : [];
        $settings = is_array($site->settings) ? $site->settings : [];
        $settings = $this->normalizeSettings($settings);
        $publishBlockers = $this->validator->publishBlockers($draftBlocks, $event);

        return [
            'status' => $site->status,
            'page_mode' => $settings['page_mode'] ?? $site->page_mode ?? 'single',
            'draft_revision' => $site->draft_revision,
            'draft_blocks' => $draftBlocks,
            'settings' => $settings,
            'live_version' => $liveVersion,
            'public_url' => $this->buildPublicUrl($event, $settings),
            'publish_blockers' => array_map(
                static fn (string $key): string => __("event_sites.blockers.{$key}"),
                $publishBlockers,
            ),
        ];
    }

    private function createDraft(TenantContext $context, Event $event): EventSite
    {
        return EventSite::query()->create([
            'tenant_id' => $context->tenant->id,
            'event_id' => $event->id,
            'status' => 'draft',
            'page_mode' => 'single',
            'draft_blocks' => $this->defaults->forEvent($event),
            'settings' => $this->normalizeSettings(['show_assistant' => false]),
            'draft_updated_by_user_id' => $context->actor->id,
            'draft_revision' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalizeSettings(array $settings): array
    {
        $settings['show_assistant'] = (bool) ($settings['show_assistant'] ?? false);

        $pageMode = $settings['page_mode'] ?? 'single';
        $settings['page_mode'] = in_array($pageMode, ['single', 'multi'], true) ? $pageMode : 'single';

        if (! isset($settings['site_background']) || ! is_array($settings['site_background'])) {
            $settings['site_background'] = [
                'type' => 'none',
                'color' => '#ffffff',
                'color_end' => '#f3f4f6',
                'image' => '',
                'overlay' => 0,
            ];
        }

        if (! isset($settings['logo']) || ! is_array($settings['logo'])) {
            $settings['logo'] = [
                'url' => '',
                'path' => '',
                'position' => 'left',
                'size' => 'md',
            ];
        }

        if (! isset($settings['pages']) || ! is_array($settings['pages']) || $settings['pages'] === []) {
            $settings['pages'] = [[
                'id' => 'home',
                'slug' => 'home',
                'title_en' => 'Home',
                'title_ar' => 'الرئيسية',
                'is_home' => true,
                'background' => [
                    'type' => 'none',
                    'color' => '#ffffff',
                    'color_end' => '#f3f4f6',
                    'image' => '',
                    'overlay' => 0,
                ],
            ]];
        }

        $prefix = $settings['public_path_prefix'] ?? 'e';
        $settings['public_path_prefix'] = in_array($prefix, ['e', 'events'], true) ? $prefix : 'e';

        $publicSlug = isset($settings['public_slug']) ? trim((string) $settings['public_slug']) : '';
        $settings['public_slug'] = $publicSlug;

        return $settings;
    }

    private function ensureChromeBlocks(EventSite $site, Event $event): EventSite
    {
        $blocks = is_array($site->draft_blocks) ? $site->draft_blocks : [];
        $types = array_map(static fn (array $block): string => (string) ($block['type'] ?? ''), $blocks);
        $seed = $this->defaults->forEvent($event);
        $changed = false;

        if (! in_array('header', $types, true)) {
            $header = collect($seed)->firstWhere('type', 'header');
            if (is_array($header)) {
                array_unshift($blocks, $header);
                $changed = true;
            }
        }

        if (! in_array('footer', $types, true)) {
            $footer = collect($seed)->firstWhere('type', 'footer');
            if (is_array($footer)) {
                $blocks[] = $footer;
                $changed = true;
            }
        }

        if (! $changed) {
            return $site;
        }

        $site->forceFill([
            'draft_blocks' => array_values($blocks),
            'draft_revision' => ((int) $site->draft_revision) + 1,
        ])->save();

        return $site->refresh();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function buildPublicUrl(Event $event, array $settings = []): string
    {
        return PublicSiteUrl::relative($event, $settings, 'en');
    }
}
