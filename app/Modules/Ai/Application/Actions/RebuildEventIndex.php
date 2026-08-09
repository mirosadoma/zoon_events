<?php

namespace App\Modules\Ai\Application\Actions;

use App\Modules\Ai\Contracts\EmbeddingProvider;
use App\Modules\Ai\Contracts\KnowledgeSourceProvider;
use App\Modules\Ai\Domain\KnowledgeChunk;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantSettings;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventKnowledgeChunk;
use Illuminate\Support\Facades\DB;

final class RebuildEventIndex
{
    /** @var list<KnowledgeSourceProvider> */
    private array $sources = [];

    public function __construct(
        private readonly EmbeddingProvider $embeddingProvider,
    ) {}

    public function addSource(KnowledgeSourceProvider $source): void
    {
        $this->sources[] = $source;
    }

    public function execute(int $tenantId, int $eventId): void
    {
        $settings = EventAssistantSettings::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        if ($settings === null) {
            $settings = EventAssistantSettings::create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'index_status' => 'pending',
            ]);
        }

        $settings->update(['index_status' => 'pending', 'index_error_code' => null]);

        try {
            $chunks = $this->extractAllChunks($tenantId, $eventId);
            $newVersion = $settings->index_version + 1;

            if ($this->embeddingProvider->isAvailable()) {
                $chunks = $this->embedChunks($chunks);
            }

            DB::transaction(function () use ($tenantId, $eventId, $chunks, $newVersion, $settings): void {
                EventKnowledgeChunk::query()
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $eventId)
                    ->where('index_version', '<', $newVersion)
                    ->delete();

                foreach ($chunks as $chunk) {
                    EventKnowledgeChunk::create([
                        'tenant_id' => $tenantId,
                        'event_id' => $eventId,
                        'index_version' => $newVersion,
                        'source_type' => $chunk->sourceType,
                        'source_id' => $chunk->sourceId,
                        'locale' => $chunk->locale,
                        'title' => $chunk->title,
                        'content' => $chunk->content,
                        'content_hash' => $chunk->contentHash(),
                        'embedding' => $chunk->embedding,
                        'embedding_model' => $chunk->embeddingModel,
                        'token_estimate' => $chunk->tokenEstimate,
                    ]);
                }

                $settings->update([
                    'index_version' => $newVersion,
                    'indexed_at' => now(),
                    'index_status' => 'ready',
                    'chunk_count' => count($chunks),
                    'index_error_code' => null,
                ]);
            });

        } catch (\Throwable $e) {
            $settings->update([
                'index_status' => 'failed',
                'index_error_code' => 'indexing_failed',
            ]);

            throw $e;
        }
    }

    /**
     * @return list<KnowledgeChunk>
     */
    private function extractAllChunks(int $tenantId, int $eventId): array
    {
        $chunks = [];

        foreach ($this->sources as $source) {
            $chunks = array_merge($chunks, $source->extract($tenantId, $eventId));
        }

        return $chunks;
    }

    /**
     * @param  list<KnowledgeChunk>  $chunks
     * @return list<KnowledgeChunk>
     */
    private function embedChunks(array $chunks): array
    {
        if ($chunks === []) {
            return [];
        }

        $batchSize = 20;
        $result = [];

        foreach (array_chunk($chunks, $batchSize) as $batch) {
            $texts = array_map(fn ($c) => $c->content, $batch);
            $locale = $batch[0]->locale ?? 'en';

            try {
                $embeddings = $this->embeddingProvider->embed($texts, $locale);

                foreach ($batch as $index => $chunk) {
                    $result[] = new KnowledgeChunk(
                        sourceType: $chunk->sourceType,
                        sourceId: $chunk->sourceId,
                        locale: $chunk->locale,
                        title: $chunk->title,
                        content: $chunk->content,
                        tokenEstimate: $chunk->tokenEstimate,
                        embedding: $embeddings[$index] ?? null,
                        embeddingModel: $this->embeddingProvider->key(),
                    );
                }
            } catch (\Throwable) {
                $result = array_merge($result, $batch);
            }
        }

        return $result;
    }
}
