<?php

namespace App\Modules\Ai\Contracts;

use App\Modules\Ai\Domain\KnowledgeChunk;

interface KnowledgeRetriever
{
    /**
     * Retrieve relevant knowledge chunks for a question.
     *
     * @return list<KnowledgeChunk>
     */
    public function retrieve(
        int $tenantId,
        int $eventId,
        int $indexVersion,
        string $query,
        string $locale,
        int $maxChunks,
        int $maxChars,
    ): array;
}
