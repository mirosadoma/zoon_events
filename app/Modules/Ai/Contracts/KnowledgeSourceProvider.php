<?php

namespace App\Modules\Ai\Contracts;

use App\Modules\Ai\Domain\KnowledgeChunk;

interface KnowledgeSourceProvider
{
    public function sourceType(): string;

    /**
     * Extract knowledge chunks from this source for the given event.
     *
     * @return list<KnowledgeChunk>
     */
    public function extract(int $tenantId, int $eventId): array;
}
