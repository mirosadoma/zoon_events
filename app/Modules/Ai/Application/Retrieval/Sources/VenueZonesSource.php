<?php

namespace App\Modules\Ai\Application\Retrieval\Sources;

use App\Modules\Ai\Application\Retrieval\KnowledgeChunker;
use App\Modules\Ai\Contracts\KnowledgeSourceProvider;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final class VenueZonesSource implements KnowledgeSourceProvider
{
    public function __construct(
        private readonly KnowledgeChunker $chunker,
    ) {}

    public function sourceType(): string
    {
        return 'venue';
    }

    public function extract(int $tenantId, int $eventId): array
    {
        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $eventId)
            ->first();

        if ($event === null) {
            return [];
        }

        $chunks = [];

        foreach ($event->venues as $venue) {
            $contentEn = $this->buildVenueContent($venue, 'en');
            if ($contentEn !== '') {
                $chunks = array_merge(
                    $chunks,
                    $this->chunker->chunk($contentEn, $this->sourceType(), "venue_{$venue->id}", 'en', $venue->name_en ?? 'Venue'),
                );
            }

            $contentAr = $this->buildVenueContent($venue, 'ar');
            if ($contentAr !== '') {
                $chunks = array_merge(
                    $chunks,
                    $this->chunker->chunk($contentAr, $this->sourceType(), "venue_{$venue->id}", 'ar', $venue->name_ar ?? 'المكان'),
                );
            }
        }

        foreach ($event->zones as $zone) {
            $contentEn = $this->buildZoneContent($zone, 'en');
            if ($contentEn !== '') {
                $chunks = array_merge(
                    $chunks,
                    $this->chunker->chunk($contentEn, 'zone', "zone_{$zone->id}", 'en', $zone->name_en ?? 'Zone'),
                );
            }

            $contentAr = $this->buildZoneContent($zone, 'ar');
            if ($contentAr !== '') {
                $chunks = array_merge(
                    $chunks,
                    $this->chunker->chunk($contentAr, 'zone', "zone_{$zone->id}", 'ar', $zone->name_ar ?? 'منطقة'),
                );
            }
        }

        return $chunks;
    }

    private function buildVenueContent($venue, string $locale): string
    {
        $parts = [];

        $name = $locale === 'ar' ? $venue->name_ar : $venue->name_en;
        if ($name) {
            $parts[] = ($locale === 'ar' ? 'المكان: ' : 'Venue: ').$name;
        }

        if ($venue->location_address) {
            $parts[] = ($locale === 'ar' ? 'العنوان: ' : 'Address: ').$venue->location_address;
        }

        if ($venue->start_at && $venue->end_at) {
            $parts[] = ($locale === 'ar' ? 'التاريخ: ' : 'Date: ').$venue->start_at->format('Y-m-d').' - '.$venue->end_at->format('Y-m-d');
        }

        return implode("\n", $parts);
    }

    private function buildZoneContent($zone, string $locale): string
    {
        $parts = [];

        $name = $locale === 'ar' ? $zone->name_ar : $zone->name_en;
        if ($name) {
            $parts[] = ($locale === 'ar' ? 'المنطقة: ' : 'Zone: ').$name;
        }

        $description = $locale === 'ar' ? ($zone->description_ar ?? $zone->description_en) : ($zone->description_en ?? $zone->description_ar);
        if ($description) {
            $parts[] = $description;
        }

        if ($zone->capacity) {
            $parts[] = ($locale === 'ar' ? 'السعة: ' : 'Capacity: ').$zone->capacity;
        }

        return implode("\n", $parts);
    }
}
