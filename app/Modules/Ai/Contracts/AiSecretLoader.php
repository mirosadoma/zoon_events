<?php

namespace App\Modules\Ai\Contracts;

interface AiSecretLoader
{
    public function load(string $reference): string;
}
