<?php

namespace App\Modules\EventSites\Infrastructure\Persistence;

use App\Modules\EventSites\Contracts\PublishedSiteReader;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSite;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSiteVersion;

final class DatabasePublishedSiteReader implements PublishedSiteReader
{
    public function getPublishedBlocksText(int $tenantId, int $eventId): array
    {
        $site = EventSite::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'published')
            ->first();

        if ($site === null || $site->live_version_id === null) {
            return [];
        }

        $version = EventSiteVersion::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('id', $site->live_version_id)
            ->first();

        if ($version === null) {
            return [];
        }

        $blocks = is_array($version->blocks) ? $version->blocks : [];
        $result = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ($block['visible'] ?? true) !== true) {
                continue;
            }

            $type = $block['type'] ?? 'unknown';
            $contentEn = $this->extractTextContent($block['content_en'] ?? []);
            $contentAr = $this->extractTextContent($block['content_ar'] ?? []);

            if ($contentEn !== '' || $contentAr !== '') {
                $result[] = [
                    'type' => $type,
                    'content_en' => $contentEn,
                    'content_ar' => $contentAr,
                ];
            }
        }

        return $result;
    }

    /** @param  array<string, mixed>|mixed  $content */
    private function extractTextContent(mixed $content): string
    {
        if (! is_array($content)) {
            return '';
        }

        $parts = [];

        foreach ($content as $key => $value) {
            if (is_string($value) && $value !== '') {
                $parts[] = $value;
            } elseif (is_array($value)) {
                $parts[] = $this->extractTextFromArray($value);
            }
        }

        return implode("\n", array_filter($parts));
    }

    /** @param  array<mixed>  $array */
    private function extractTextFromArray(array $array): string
    {
        $parts = [];

        foreach ($array as $item) {
            if (is_string($item) && $item !== '') {
                $parts[] = $item;
            } elseif (is_array($item)) {
                foreach ($item as $subItem) {
                    if (is_string($subItem) && $subItem !== '') {
                        $parts[] = $subItem;
                    }
                }
            }
        }

        return implode("\n", $parts);
    }
}
