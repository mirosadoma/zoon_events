<?php

namespace App\Modules\Ai\Application\Retrieval;

use App\Modules\Ai\Infrastructure\Persistence\Models\EventKnowledgeChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LexicalCandidateFinder
{
    private const MIN_FULLTEXT_QUERY_LENGTH = 3;

    /**
     * @return Collection<int, EventKnowledgeChunk>
     */
    public function find(
        int $tenantId,
        int $eventId,
        int $indexVersion,
        string $query,
        string $locale,
        int $maxCandidates,
    ): Collection {
        $useFullText = mb_strlen($query) >= self::MIN_FULLTEXT_QUERY_LENGTH
            && $this->supportsFullText();

        if ($useFullText) {
            return $this->findWithFullText($tenantId, $eventId, $indexVersion, $query, $locale, $maxCandidates);
        }

        return $this->findWithLike($tenantId, $eventId, $indexVersion, $query, $locale, $maxCandidates);
    }

    /**
     * @return Collection<int, EventKnowledgeChunk>
     */
    private function findWithFullText(
        int $tenantId,
        int $eventId,
        int $indexVersion,
        string $query,
        string $locale,
        int $maxCandidates,
    ): Collection {
        $searchQuery = $this->prepareFullTextQuery($query);

        return EventKnowledgeChunk::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('index_version', $indexVersion)
            ->where('locale', $locale)
            ->whereRaw(
                'MATCH(content, title) AGAINST(? IN NATURAL LANGUAGE MODE)',
                [$searchQuery],
            )
            ->orderByRaw(
                'MATCH(content, title) AGAINST(? IN NATURAL LANGUAGE MODE) DESC',
                [$searchQuery],
            )
            ->limit($maxCandidates)
            ->get();
    }

    /**
     * @return Collection<int, EventKnowledgeChunk>
     */
    private function findWithLike(
        int $tenantId,
        int $eventId,
        int $indexVersion,
        string $query,
        string $locale,
        int $maxCandidates,
    ): Collection {
        $words = $this->extractSearchWords($query);

        $builder = EventKnowledgeChunk::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('index_version', $indexVersion)
            ->where('locale', $locale);

        if ($words !== []) {
            $builder->where(function ($q) use ($words): void {
                foreach ($words as $word) {
                    $q->orWhere('content', 'LIKE', "%{$word}%")
                        ->orWhere('title', 'LIKE', "%{$word}%");
                }
            });
        }

        return $builder->limit($maxCandidates)->get();
    }

    private function prepareFullTextQuery(string $query): string
    {
        $query = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query);

        return trim($query);
    }

    /**
     * @return list<string>
     */
    private function extractSearchWords(string $query): array
    {
        $words = preg_split('/\s+/', mb_strtolower($query));
        $words = array_filter($words, fn (string $w) => mb_strlen($w) >= 2);

        return array_values(array_unique($words));
    }

    private function supportsFullText(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
}
