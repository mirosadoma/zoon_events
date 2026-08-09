<?php

namespace App\Modules\Ai\Domain;

final readonly class AiAnswer
{
    /**
     * @param  list<array{source_type: string, source_id: string, title: ?string}>  $citations
     */
    public function __construct(
        public AssistantOutcome $outcome,
        public ?string $answer,
        public array $citations,
        public string $locale,
        public ?string $providerKey = null,
        public ?int $latencyMs = null,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
    ) {}

    public static function answered(
        string $answer,
        array $citations,
        string $locale,
        string $providerKey,
        int $latencyMs,
        int $promptTokens,
        int $completionTokens,
    ): self {
        return new self(
            AssistantOutcome::Answered,
            $answer,
            $citations,
            $locale,
            $providerKey,
            $latencyMs,
            $promptTokens,
            $completionTokens,
        );
    }

    public static function unanswered(string $locale): self
    {
        return new self(AssistantOutcome::Unanswered, null, [], $locale);
    }

    public static function refused(string $locale): self
    {
        return new self(AssistantOutcome::Refused, null, [], $locale);
    }

    public static function throttled(string $locale): self
    {
        return new self(AssistantOutcome::Throttled, null, [], $locale);
    }

    public static function unavailable(string $locale): self
    {
        return new self(AssistantOutcome::Unavailable, null, [], $locale);
    }
}
