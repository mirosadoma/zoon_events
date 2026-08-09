<?php

namespace App\Modules\EventSites\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSite;

final class PublicSiteUrl
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public static function pathSlug(Event $event, array $settings = []): string
    {
        $custom = isset($settings['public_slug']) ? trim((string) $settings['public_slug']) : '';
        if ($custom !== '') {
            return mb_strtolower($custom);
        }

        return (string) $event->slug;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function pathPrefix(array $settings = []): string
    {
        $prefix = isset($settings['public_path_prefix']) ? (string) $settings['public_path_prefix'] : 'e';

        return $prefix === 'events' ? 'events' : 'e';
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function relative(Event $event, array $settings = [], string $locale = 'en'): string
    {
        $prefix = self::pathPrefix($settings);
        $slug = self::pathSlug($event, $settings);

        return '/'.$locale.'/'.$prefix.'/'.$slug;
    }

    public static function findEventByPublicSlug(string $slug): ?Event
    {
        $normalized = mb_strtolower(trim($slug));
        if ($normalized === '') {
            return null;
        }

        $byEventSlug = Event::query()->where('slug', $normalized)->first();
        if ($byEventSlug !== null) {
            return $byEventSlug;
        }

        $site = EventSite::query()
            ->where('settings->public_slug', $normalized)
            ->orderByDesc('id')
            ->first();

        if ($site === null) {
            return null;
        }

        return Event::query()->find($site->event_id);
    }
}
