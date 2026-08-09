<?php

namespace App\Modules\Ai\Application\Retrieval\Sources;

use App\Modules\Ai\Application\Retrieval\KnowledgeChunker;
use App\Modules\Ai\Contracts\KnowledgeSourceProvider;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;

final class AgendaSpeakersSource implements KnowledgeSourceProvider
{
    public function __construct(
        private readonly KnowledgeChunker $chunker,
    ) {}

    public function sourceType(): string
    {
        return 'agenda';
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

        $agendaItems = $event->agendaItems()->get();

        if ($agendaItems->isEmpty()) {
            return [];
        }

        $chunks = [];

        foreach ($agendaItems as $item) {
            $contentEn = $this->buildAgendaItemContent($item, 'en');
            if ($contentEn !== '') {
                $chunks = array_merge(
                    $chunks,
                    $this->chunker->chunk(
                        $contentEn,
                        $this->sourceType(),
                        "agenda_{$item->id}",
                        'en',
                        $item->title_en ?? 'Session',
                    ),
                );
            }

            $contentAr = $this->buildAgendaItemContent($item, 'ar');
            if ($contentAr !== '') {
                $chunks = array_merge(
                    $chunks,
                    $this->chunker->chunk(
                        $contentAr,
                        $this->sourceType(),
                        "agenda_{$item->id}",
                        'ar',
                        $item->title_ar ?? 'جلسة',
                    ),
                );
            }
        }

        return $chunks;
    }

    private function buildAgendaItemContent(EventAgendaItem $item, string $locale): string
    {
        $parts = [];

        $title = $locale === 'ar' ? $item->title_ar : $item->title_en;
        if ($title) {
            $parts[] = "Session: {$title}";
        }

        $description = $locale === 'ar' ? $item->description_ar : $item->description_en;
        if ($description) {
            $parts[] = $description;
        }

        if ($item->start_at) {
            $parts[] = ($locale === 'ar' ? 'الوقت: ' : 'Time: ').$item->start_at->format('H:i');
            if ($item->end_at) {
                $parts[count($parts) - 1] .= ' - '.$item->end_at->format('H:i');
            }
        }

        $location = $locale === 'ar' ? ($item->location_ar ?? $item->location_en) : ($item->location_en ?? $item->location_ar);
        if ($location) {
            $parts[] = ($locale === 'ar' ? 'المكان: ' : 'Location: ').$location;
        }

        $speaker = $locale === 'ar' ? ($item->speaker_ar ?? $item->speaker_en) : ($item->speaker_en ?? $item->speaker_ar);
        if ($speaker) {
            $parts[] = ($locale === 'ar' ? 'المتحدث: ' : 'Speaker: ').$speaker;
        }

        return implode("\n", $parts);
    }
}
