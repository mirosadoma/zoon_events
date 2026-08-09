<?php

namespace App\Modules\Ai\Application\Retrieval;

use App\Modules\Ai\Contracts\KnowledgeRetriever;
use App\Modules\Ai\Domain\KnowledgeChunk;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventKnowledgeChunk;

final class DatabaseKnowledgeRetriever implements KnowledgeRetriever
{
    public function __construct(
        private readonly LexicalCandidateFinder $candidateFinder,
        private readonly CosineReranker $reranker,
    ) {}

    public function retrieve(
        int $tenantId,
        int $eventId,
        int $indexVersion,
        string $query,
        string $locale,
        int $maxChunks,
        int $maxChars,
    ): array {
        $candidateCount = (int) config('ai.assistant.retrieval_candidates', 50);

        $candidates = $this->candidateFinder->find(
            $tenantId,
            $eventId,
            $indexVersion,
            $query,
            $locale,
            $candidateCount,
        );

        if ($candidates->isEmpty()) {
            return [];
        }

        $topK = (int) config('ai.assistant.retrieval_top_k', 5);
        $reranked = $this->reranker->rerank($candidates, $query, $locale, min($topK, $maxChunks));

        $chunks = [];
        $totalChars = 0;

        foreach ($reranked as $model) {
            $contentLength = mb_strlen($model->content);

            if ($totalChars + $contentLength > $maxChars) {
                break;
            }

            $chunks[] = $this->toKnowledgeChunk($model);
            $totalChars += $contentLength;
        }

        return $chunks;
    }

    private function toKnowledgeChunk(EventKnowledgeChunk $model): KnowledgeChunk
    {
        return new KnowledgeChunk(
            sourceType: $model->source_type,
            sourceId: $model->source_id,
            locale: $model->locale,
            title: $model->title,
            content: $model->content,
            tokenEstimate: $model->token_estimate,
            embedding: $model->embedding,
            embeddingModel: $model->embedding_model,
        );
    }
}
