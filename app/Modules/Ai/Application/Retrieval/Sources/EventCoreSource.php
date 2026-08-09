<?php

namespace App\Modules\Ai\Application\Retrieval\Sources;

use App\Modules\Ai\Application\Retrieval\KnowledgeChunker;
use App\Modules\Ai\Contracts\KnowledgeSourceProvider;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final class EventCoreSource implements KnowledgeSourceProvider
{
    public function __construct(
        private readonly KnowledgeChunker $chunker,
    ) {}

    public function sourceType(): string
    {
        return 'event_core';
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

        $contentEn = $this->buildEventContent($event, 'en');
        if ($contentEn !== '') {
            $chunks = array_merge(
                $chunks,
                $this->chunker->chunk($contentEn, $this->sourceType(), 'core', 'en', $event->name_en),
            );
        }

        $contentAr = $this->buildEventContent($event, 'ar');
        if ($contentAr !== '') {
            $chunks = array_merge(
                $chunks,
                $this->chunker->chunk($contentAr, $this->sourceType(), 'core', 'ar', $event->name_ar),
            );
        }

        return $chunks;
    }

    private function buildEventContent(Event $event, string $locale): string
    {
        $parts = [];

        $name = $locale === 'ar' ? $event->name_ar : $event->name_en;
        if ($name) {
            $parts[] = "Event: {$name}";
        }

        $description = $locale === 'ar' ? $event->description_ar : $event->description_en;
        if ($description) {
            $parts[] = $description;
        }

        $locationName = $locale === 'ar' ? $event->location_name_ar : $event->location_name_en;
        if ($locationName) {
            $parts[] = ($locale === 'ar' ? 'الموقع: ' : 'Location: ').$locationName;
        }

        $locationAddress = $locale === 'ar' ? $event->location_address_ar : $event->location_address_en;
        if ($locationAddress) {
            $parts[] = ($locale === 'ar' ? 'العنوان: ' : 'Address: ').$locationAddress;
        }

        if ($event->start_at && $event->end_at) {
            $dateLabel = $locale === 'ar' ? 'التاريخ: ' : 'Date: ';
            $parts[] = $dateLabel.$event->start_at->format('Y-m-d').' - '.$event->end_at->format('Y-m-d');
        }

        if ($event->timezone) {
            $parts[] = ($locale === 'ar' ? 'المنطقة الزمنية: ' : 'Timezone: ').$event->timezone;
        }

        return implode("\n", $parts);
    }
}
