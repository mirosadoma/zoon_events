<?php

namespace App\Modules\Ai\Contracts;

use App\Modules\Ai\Domain\AiProviderException;

interface EmbeddingProvider
{
    public function key(): string;

    public function isAvailable(): bool;

    /**
     * @param  list<string>  $texts
     * @return list<list<float>> one vector per input, same order
     *
     * @throws AiProviderException
     */
    public function embed(array $texts, string $locale): array;
}
