<?php

namespace App\Modules\Ai\Application\Retrieval;

use App\Modules\Ai\Contracts\EmbeddingProvider;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventKnowledgeChunk;
use Illuminate\Support\Collection;

final class CosineReranker
{
    public function __construct(
        private readonly EmbeddingProvider $embeddingProvider,
    ) {}

    /**
     * @param  Collection<int, EventKnowledgeChunk>  $candidates
     * @return Collection<int, EventKnowledgeChunk>
     */
    public function rerank(
        Collection $candidates,
        string $query,
        string $locale,
        int $topK,
    ): Collection {
        if ($candidates->isEmpty()) {
            return $candidates;
        }

        $candidatesWithEmbeddings = $candidates->filter(
            fn (EventKnowledgeChunk $c) => $c->embedding !== null && $c->embedding !== [],
        );

        if ($candidatesWithEmbeddings->isEmpty() || ! $this->embeddingProvider->isAvailable()) {
            return $candidates->take($topK);
        }

        try {
            $queryEmbedding = $this->embeddingProvider->embed([$query], $locale)[0] ?? [];
        } catch (\Throwable) {
            return $candidates->take($topK);
        }

        if ($queryEmbedding === []) {
            return $candidates->take($topK);
        }

        $scored = $candidatesWithEmbeddings->map(function (EventKnowledgeChunk $chunk) use ($queryEmbedding): array {
            $similarity = $this->cosineSimilarity($queryEmbedding, $chunk->embedding ?? []);

            return ['chunk' => $chunk, 'score' => $similarity];
        });

        $unscored = $candidates->filter(
            fn (EventKnowledgeChunk $c) => $c->embedding === null || $c->embedding === [],
        )->map(fn (EventKnowledgeChunk $chunk): array => ['chunk' => $chunk, 'score' => 0.0]);

        return $scored->merge($unscored)
            ->sortByDesc('score')
            ->take($topK)
            ->map(fn (array $item) => $item['chunk'])
            ->values();
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || $a === []) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $valA) {
            $valB = $b[$i];
            $dotProduct += $valA * $valB;
            $normA += $valA * $valA;
            $normB += $valB * $valB;
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }
}
