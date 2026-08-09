<?php

namespace App\Modules\Ai\Application\Retrieval\Sources;

use App\Modules\Ai\Application\Retrieval\KnowledgeChunker;
use App\Modules\Ai\Contracts\KnowledgeSourceProvider;
use App\Modules\EventSites\Contracts\PublishedSiteReader;

final class PublishedSiteSource implements KnowledgeSourceProvider
{
    public function __construct(
        private readonly PublishedSiteReader $siteReader,
        private readonly KnowledgeChunker $chunker,
    ) {}

    public function sourceType(): string
    {
        return 'site_block';
    }

    public function extract(int $tenantId, int $eventId): array
    {
        $blocks = $this->siteReader->getPublishedBlocksText($tenantId, $eventId);
        $chunks = [];

        foreach ($blocks as $index => $block) {
            $type = $block['type'] ?? 'unknown';
            $title = $this->formatBlockTitle($type);

            if (($block['content_en'] ?? '') !== '') {
                $chunks = array_merge(
                    $chunks,
                    $this->chunker->chunk(
                        $block['content_en'],
                        $this->sourceType(),
                        "block_{$index}",
                        'en',
                        $title,
                    ),
                );
            }

            if (($block['content_ar'] ?? '') !== '') {
                $chunks = array_merge(
                    $chunks,
                    $this->chunker->chunk(
                        $block['content_ar'],
                        $this->sourceType(),
                        "block_{$index}",
                        'ar',
                        $title,
                    ),
                );
            }
        }

        return $chunks;
    }

    private function formatBlockTitle(string $type): string
    {
        return match ($type) {
            'hero' => 'Event Overview',
            'about' => 'About the Event',
            'agenda' => 'Event Agenda',
            'speakers' => 'Speakers',
            'venue' => 'Venue Information',
            'faq' => 'FAQ',
            'sponsors' => 'Sponsors',
            'gallery' => 'Gallery',
            'register_cta' => 'Registration',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
