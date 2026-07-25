<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final class EventSlug
{
    /**
     * Build a URL slug from a title (Arabic or English).
     * Spaces become hyphens; letters and digits are kept.
     */
    public static function fromTitle(string $title): string
    {
        $slug = trim($title);
        $slug = preg_replace('/\s+/u', '-', $slug) ?? '';
        $slug = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $slug) ?? '';
        $slug = preg_replace('/-+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        $slug = mb_strtolower($slug, 'UTF-8');

        if ($slug === '') {
            return 'event';
        }

        return mb_substr($slug, 0, 100);
    }

    public static function fromNames(string $nameEn, string $nameAr): string
    {
        $nameEn = trim($nameEn);
        $nameAr = trim($nameAr);

        return self::fromTitle($nameEn !== '' ? $nameEn : $nameAr);
    }

    public static function uniqueForTenant(string|int $tenantId, string $base, string|int|null $ignoreEventId = null): string
    {
        $base = $base !== '' ? mb_substr($base, 0, 100) : 'event';
        $slug = $base;
        $suffix = 2;

        while (self::existsForTenant($tenantId, $slug, $ignoreEventId)) {
            $suffixPart = '-'.$suffix;
            $maxBase = 100 - strlen($suffixPart);
            $slug = mb_substr($base, 0, max(1, $maxBase)).$suffixPart;
            $suffix++;
        }

        return $slug;
    }

    private static function existsForTenant(string|int $tenantId, string $slug, string|int|null $ignoreEventId): bool
    {
        $query = Event::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug);

        if ($ignoreEventId !== null && $ignoreEventId !== '') {
            $query->where('id', '!=', $ignoreEventId);
        }

        return $query->exists();
    }
}
