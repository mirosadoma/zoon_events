<?php

namespace App\Modules\Ai\Testing;

use App\Modules\Ai\Contracts\EmbeddingProvider;

final class FakeEmbeddingProvider implements EmbeddingProvider
{
    private const VECTOR_DIMENSIONS = 256;

    private bool $available = true;

    /** @var list<array{texts: list<string>, locale: string}> */
    public array $calls = [];

    public function key(): string
    {
        return 'fake';
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function embed(array $texts, string $locale): array
    {
        $this->calls[] = ['texts' => $texts, 'locale' => $locale];

        return array_map(fn (string $text) => $this->generatePseudoVector($text, $locale), $texts);
    }

    /**
     * @return list<float>
     */
    private function generatePseudoVector(string $text, string $locale): array
    {
        $seed = crc32($text.$locale);
        mt_srand($seed);

        $vector = [];
        for ($i = 0; $i < self::VECTOR_DIMENSIONS; $i++) {
            $vector[] = (mt_rand(0, 10000) / 10000) * 2 - 1;
        }

        $magnitude = sqrt(array_sum(array_map(fn (float $v) => $v * $v, $vector)));
        if ($magnitude > 0) {
            $vector = array_map(fn (float $v) => $v / $magnitude, $vector);
        }

        mt_srand();

        return $vector;
    }

    public function reset(): void
    {
        $this->available = true;
        $this->calls = [];
    }
}
