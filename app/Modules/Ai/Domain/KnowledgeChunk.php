<?php

namespace App\Modules\Ai\Domain;

final readonly class KnowledgeChunk
{
    /**
     * @param  list<float>|null  $embedding
     */
    public function __construct(
        public string $sourceType,
        public string $sourceId,
        public string $locale,
        public ?string $title,
        public string $content,
        public int $tokenEstimate,
        public ?array $embedding = null,
        public ?string $embeddingModel = null,
    ) {}

    public function contentHash(): string
    {
        return hash('sha256', $this->content);
    }
}
