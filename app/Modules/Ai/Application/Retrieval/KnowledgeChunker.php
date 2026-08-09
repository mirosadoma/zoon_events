<?php

namespace App\Modules\Ai\Application\Retrieval;

use App\Modules\Ai\Domain\KnowledgeChunk;

final class KnowledgeChunker
{
    private const MAX_CHUNK_CHARS = 1200;

    private const OVERLAP_CHARS = 100;

    /**
     * @return list<KnowledgeChunk>
     */
    public function chunk(
        string $content,
        string $sourceType,
        string $sourceId,
        string $locale,
        ?string $title = null,
    ): array {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        if (mb_strlen($content) <= self::MAX_CHUNK_CHARS) {
            return [
                new KnowledgeChunk(
                    sourceType: $sourceType,
                    sourceId: $sourceId,
                    locale: $locale,
                    title: $title,
                    content: $content,
                    tokenEstimate: $this->estimateTokens($content),
                ),
            ];
        }

        $chunks = [];
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $currentChunk = '';
        $chunkIndex = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($currentChunk.$paragraph) <= self::MAX_CHUNK_CHARS) {
                $currentChunk .= ($currentChunk !== '' ? "\n\n" : '').$paragraph;
            } else {
                if ($currentChunk !== '') {
                    $chunks[] = $this->createChunk($currentChunk, $sourceType, $sourceId, $locale, $title, $chunkIndex);
                    $chunkIndex++;
                    $overlap = mb_substr($currentChunk, -self::OVERLAP_CHARS);
                    $currentChunk = $overlap.$paragraph;
                } else {
                    $sentenceChunks = $this->splitLongParagraph($paragraph, $sourceType, $sourceId, $locale, $title, $chunkIndex);
                    $chunks = array_merge($chunks, $sentenceChunks);
                    $chunkIndex += count($sentenceChunks);
                    $currentChunk = '';
                }
            }
        }

        if ($currentChunk !== '') {
            $chunks[] = $this->createChunk($currentChunk, $sourceType, $sourceId, $locale, $title, $chunkIndex);
        }

        return $chunks;
    }

    /**
     * @return list<KnowledgeChunk>
     */
    private function splitLongParagraph(
        string $paragraph,
        string $sourceType,
        string $sourceId,
        string $locale,
        ?string $title,
        int $startIndex,
    ): array {
        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph);
        $currentChunk = '';
        $chunkIndex = $startIndex;

        foreach ($sentences as $sentence) {
            if (mb_strlen($currentChunk.$sentence) <= self::MAX_CHUNK_CHARS) {
                $currentChunk .= ($currentChunk !== '' ? ' ' : '').$sentence;
            } else {
                if ($currentChunk !== '') {
                    $chunks[] = $this->createChunk($currentChunk, $sourceType, $sourceId, $locale, $title, $chunkIndex);
                    $chunkIndex++;
                }
                if (mb_strlen($sentence) > self::MAX_CHUNK_CHARS) {
                    $currentChunk = mb_substr($sentence, 0, self::MAX_CHUNK_CHARS);
                } else {
                    $currentChunk = $sentence;
                }
            }
        }

        if ($currentChunk !== '') {
            $chunks[] = $this->createChunk($currentChunk, $sourceType, $sourceId, $locale, $title, $chunkIndex);
        }

        return $chunks;
    }

    private function createChunk(
        string $content,
        string $sourceType,
        string $sourceId,
        string $locale,
        ?string $title,
        int $index,
    ): KnowledgeChunk {
        $chunkTitle = $title;
        if ($index > 0 && $title !== null) {
            $chunkTitle = "{$title} (part ".($index + 1).')';
        }

        return new KnowledgeChunk(
            sourceType: $sourceType,
            sourceId: "{$sourceId}:{$index}",
            locale: $locale,
            title: $chunkTitle,
            content: trim($content),
            tokenEstimate: $this->estimateTokens($content),
        );
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }
}
