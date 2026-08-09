<?php

namespace App\Modules\EventSites\Application\Support;

use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantSettings;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Application\Support\PublicRegistrationUrlBuilder;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use App\Modules\EventSites\Domain\SiteBlockType;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSite;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSiteVersion;
use Illuminate\Support\Facades\Storage;

final readonly class PublishedSitePresenter
{
    public function __construct(
        private PublicRegistrationUrlBuilder $registrationUrls,
    ) {}

    /**
     * Present the published site for public consumption.
     *
     * @return array<string, mixed>
     */
    public function present(Event $event, EventSite $site, EventSiteVersion $version, string $locale, ?string $pageSlug = null): array
    {
        $branding = EventBranding::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->first();

        $settings = is_array($site->settings) ? $site->settings : [];
        $pages = is_array($settings['pages'] ?? null) ? $settings['pages'] : [[
            'id' => 'home',
            'slug' => 'home',
            'title_en' => 'Home',
            'title_ar' => 'الرئيسية',
            'is_home' => true,
            'background' => ['type' => 'none'],
        ]];

        $activePage = null;
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            if ($pageSlug === null && ($page['is_home'] ?? false)) {
                $activePage = $page;
                break;
            }
            if ($pageSlug !== null && ($page['slug'] ?? null) === $pageSlug) {
                $activePage = $page;
                break;
            }
        }
        if ($activePage === null) {
            $activePage = $pages[0] ?? ['id' => 'home', 'slug' => 'home', 'title_en' => 'Home', 'title_ar' => 'الرئيسية', 'is_home' => true];
        }

        $pageId = (string) ($activePage['id'] ?? 'home');
        $blocks = is_array($version->blocks) ? $version->blocks : [];
        $visibleBlocks = array_values(array_filter(
            $blocks,
            static function (array $b) use ($pageId): bool {
                if (($b['visible'] ?? true) !== true) {
                    return false;
                }
                $blockPage = (string) ($b['page_id'] ?? $b['options']['page_id'] ?? 'home');

                return $blockPage === $pageId || in_array(($b['type'] ?? ''), ['header', 'footer'], true);
            },
        ));

        $resolvedBlocks = array_map(
            fn (array $block): array => $this->resolveBlock($block, $event, $locale),
            $visibleBlocks,
        );

        $assistant = EventAssistantSettings::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->first();

        $navPages = array_values(array_map(static function (array $page) use ($locale): array {
            return [
                'id' => $page['id'] ?? 'home',
                'slug' => $page['slug'] ?? 'home',
                'title' => $locale === 'ar'
                    ? ($page['title_ar'] ?? $page['title_en'] ?? $page['slug'] ?? 'Home')
                    : ($page['title_en'] ?? $page['title_ar'] ?? $page['slug'] ?? 'Home'),
                'is_home' => (bool) ($page['is_home'] ?? false),
            ];
        }, array_filter($pages, 'is_array')));

        $siteLogo = null;
        if (isset($settings['logo']) && is_array($settings['logo']) && ! empty($settings['logo']['url'])) {
            $siteLogo = [
                'url' => $settings['logo']['url'] ?? '',
                'path' => $settings['logo']['path'] ?? '',
                'position' => $settings['logo']['position'] ?? 'left',
                'size' => $settings['logo']['size'] ?? 'md',
            ];
        }

        return [
            'event' => [
                'slug' => $event->slug,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
                'start_at' => EventWallClockDateTime::toIso8601($event->start_at, $event->timezone),
                'end_at' => EventWallClockDateTime::toIso8601($event->end_at, $event->timezone),
                'timezone' => $event->timezone,
            ],
            'theme' => $this->presentTheme($branding),
            'page_mode' => $settings['page_mode'] ?? $site->page_mode ?? 'single',
            'pages' => $navPages,
            'current_page' => [
                'id' => $pageId,
                'slug' => $activePage['slug'] ?? 'home',
                'title' => $locale === 'ar'
                    ? ($activePage['title_ar'] ?? $activePage['title_en'] ?? 'Home')
                    : ($activePage['title_en'] ?? $activePage['title_ar'] ?? 'Home'),
                'background' => $activePage['background'] ?? ($settings['site_background'] ?? ['type' => 'none']),
            ],
            'site_background' => $settings['site_background'] ?? ['type' => 'none'],
            'logo' => $siteLogo,
            'blocks' => $resolvedBlocks,
            'assistant' => [
                'enabled' => (bool) ($assistant?->enabled ?? ($settings['show_assistant'] ?? false)),
                'display_name' => [
                    'en' => $assistant?->display_name_en ?: 'Event Assistant',
                    'ar' => $assistant?->display_name_ar ?: 'مساعد الحدث',
                ],
                'greeting' => [
                    'en' => $assistant?->greeting_en ?: 'Hello! How can I help you with this event?',
                    'ar' => $assistant?->greeting_ar ?: 'مرحباً! كيف يمكنني مساعدتك بخصوص هذا الحدث؟',
                ],
            ],
            'register_url' => $this->registrationUrls->forEvent($event),
            'site_base_url' => PublicSiteUrl::relative($event, $settings, $locale),
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function resolveBlock(array $block, Event $event, string $locale): array
    {
        $type = $block['type'] ?? '';
        $resolved = [];

        $result = [
            'id' => $block['id'] ?? '',
            'type' => $type,
            'page_id' => $block['page_id'] ?? ($block['options']['page_id'] ?? 'home'),
            'content_en' => $block['content_en'] ?? [],
            'content_ar' => $block['content_ar'] ?? [],
            'options' => $block['options'] ?? [],
            'refs' => $block['refs'] ?? [],
        ];

        switch ($type) {
            case SiteBlockType::Agenda->value:
                $resolved = $this->resolveAgendaData($event, $locale);
                break;

            case SiteBlockType::Speakers->value:
                $resolved = $this->resolveSpeakersData($event, $locale);
                break;

            case SiteBlockType::Venue->value:
                $resolved = $this->resolveVenueData($event, $locale);
                break;

            case SiteBlockType::Gallery->value:
                $resolved = $this->resolveGalleryData($event, $block);
                break;

            case SiteBlockType::Hero->value:
            case SiteBlockType::About->value:
            case SiteBlockType::Sponsors->value:
                $resolved = $this->resolveImageRefs($block);
                break;

            case SiteBlockType::RegisterCta->value:
                $resolved = [
                    'register_url' => $this->registrationUrls->forEvent($event),
                    'registration_open' => in_array($event->status, ['published', 'registration_open'], true),
                ];
                break;
        }

        $result['resolved'] = $resolved;

        return $result;
    }

    /** @return array<string, mixed> */
    public function resolveAgendaData(Event $event, string $locale): array
    {
        $items = $event->agendaItems()
            ->orderBy('agenda_date')
            ->orderBy('start_at')
            ->orderBy('sort_order')
            ->get();

        if ($items->isEmpty()) {
            return ['items' => [], 'empty' => true];
        }

        return [
            'items' => $items->map(fn (EventAgendaItem $item): array => [
                'id' => (string) $item->id,
                'title' => $locale === 'ar'
                    ? ($item->title_ar ?: $item->title_en)
                    : ($item->title_en ?: $item->title_ar),
                'description' => $locale === 'ar'
                    ? ($item->description_ar ?: $item->description_en)
                    : ($item->description_en ?: $item->description_ar),
                'speaker' => $item->speaker,
                'date' => $item->agenda_date instanceof \DateTimeInterface
                    ? $item->agenda_date->format('Y-m-d')
                    : ($item->agenda_date ? (string) $item->agenda_date : null),
                'start_at' => $item->start_at?->format('H:i'),
                'end_at' => $item->end_at?->format('H:i'),
            ])->all(),
            'empty' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function resolveSpeakersData(Event $event, string $locale): array
    {
        $speakers = $event->agendaItems()
            ->whereNotNull('speaker')
            ->where('speaker', '!=', '')
            ->pluck('speaker')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($speakers)) {
            return ['speakers' => [], 'empty' => true];
        }

        return [
            'speakers' => array_map(static fn (string $name): array => [
                'name' => $name,
            ], $speakers),
            'empty' => false,
        ];
    }

    /**
     * Preview payloads for the organizer site builder (locale-keyed).
     *
     * @return array{agenda: array{en: array<string, mixed>, ar: array<string, mixed>>, speakers: array{en: array<string, mixed>, ar: array<string, mixed>}, venue: array{en: array<string, mixed>, ar: array<string, mixed>}}
     */
    public function builderPreview(Event $event): array
    {
        return [
            'agenda' => [
                'en' => $this->resolveAgendaData($event, 'en'),
                'ar' => $this->resolveAgendaData($event, 'ar'),
            ],
            'speakers' => [
                'en' => $this->resolveSpeakersData($event, 'en'),
                'ar' => $this->resolveSpeakersData($event, 'ar'),
            ],
            'venue' => [
                'en' => $this->resolveVenueData($event, 'en'),
                'ar' => $this->resolveVenueData($event, 'ar'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function resolveVenueData(Event $event, string $locale): array
    {
        $venues = $event->venues()->get();

        if ($venues->isEmpty()) {
            return [
                'venues' => [],
                'location' => [
                    'name' => $locale === 'ar'
                        ? ($event->location_name_ar ?: $event->location_name_en)
                        : ($event->location_name_en ?: $event->location_name_ar),
                    'address' => $locale === 'ar'
                        ? ($event->location_address_ar ?: $event->location_address_en)
                        : ($event->location_address_en ?: $event->location_address_ar),
                ],
            ];
        }

        return [
            'venues' => $venues->map(fn ($venue): array => [
                'id' => (string) $venue->id,
                'name' => $locale === 'ar'
                    ? ($venue->name_ar ?? $venue->name ?? '')
                    : ($venue->name ?? $venue->name_ar ?? ''),
            ])->all(),
            'location' => [
                'name' => $locale === 'ar'
                    ? ($event->location_name_ar ?: $event->location_name_en)
                    : ($event->location_name_en ?: $event->location_name_ar),
                'address' => $locale === 'ar'
                    ? ($event->location_address_ar ?: $event->location_address_en)
                    : ($event->location_address_en ?: $event->location_address_ar),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function resolveGalleryData(Event $event, array $block): array
    {
        $refs = $block['refs'] ?? [];
        $imagePaths = $refs['images'] ?? [];

        if (! is_array($imagePaths) || empty($imagePaths)) {
            $eventImages = $event->images()->limit(20)->get();

            return [
                'images' => $eventImages->map(fn ($img): array => [
                    'url' => $img->path ? Storage::disk('public')->url($img->path) : null,
                    'alt' => $img->alt ?? '',
                ])->filter(fn (array $img): bool => $img['url'] !== null)->values()->all(),
                'empty' => $eventImages->isEmpty(),
            ];
        }

        return [
            'images' => array_map(fn (string $path): array => [
                'url' => Storage::disk('public')->url($path),
                'alt' => '',
            ], $imagePaths),
            'empty' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function resolveImageRefs(array $block): array
    {
        $refs = $block['refs'] ?? [];
        $resolved = [];

        foreach ($refs as $key => $path) {
            if (is_string($path) && $path !== '') {
                $resolved[$key.'_url'] = Storage::disk('public')->url($path);
            } elseif (is_array($path)) {
                $resolved[$key.'_urls'] = array_map(
                    static fn (string $p): string => Storage::disk('public')->url($p),
                    $path,
                );
            }
        }

        return $resolved;
    }

    /** @return array<string, mixed>|null */
    private function presentTheme(?EventBranding $branding): ?array
    {
        if ($branding === null) {
            return null;
        }

        $theme = is_array($branding->theme_config) ? $branding->theme_config : [];
        $result = [
            'colors' => $theme['colors'] ?? [],
        ];

        if (isset($theme['logo_path']) && is_string($theme['logo_path']) && $theme['logo_path'] !== '') {
            $result['logo_url'] = Storage::disk('public')->url($theme['logo_path']);
        }

        return $result;
    }
}
