<?php

namespace App\Modules\Ai\Application\Retrieval\Sources;

use App\Modules\Ai\Application\Retrieval\KnowledgeChunker;
use App\Modules\Ai\Contracts\KnowledgeSourceProvider;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantFaq;

final class OrganizerFaqSource implements KnowledgeSourceProvider
{
    public function __construct(
        private readonly KnowledgeChunker $chunker,
    ) {}

    public function sourceType(): string
    {
        return 'organizer_faq';
    }

    public function extract(int $tenantId, int $eventId): array
    {
        $faqs = EventAssistantFaq::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $chunks = [];

        foreach ($faqs as $faq) {
            $sourceId = 'faq:'.$faq->id;

            $contentEn = "Q: {$faq->question_en}\nA: {$faq->answer_en}";
            $chunks = array_merge(
                $chunks,
                $this->chunker->chunk($contentEn, $this->sourceType(), $sourceId, 'en', $faq->question_en),
            );

            $contentAr = "Q: {$faq->question_ar}\nA: {$faq->answer_ar}";
            $chunks = array_merge(
                $chunks,
                $this->chunker->chunk($contentAr, $this->sourceType(), $sourceId, 'ar', $faq->question_ar),
            );
        }

        return $chunks;
    }
}
