<?php

namespace App\Modules\EventSites\Contracts;

interface PublishedSiteReader
{
    /**
     * Get published block text for the AI module to index.
     *
     * @return array<int, array{type: string, content_en: string, content_ar: string}>
     */
    public function getPublishedBlocksText(int $tenantId, int $eventId): array;
}
