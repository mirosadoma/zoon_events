<?php

namespace App\Modules\Tenancy\Application\Boundaries;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use InvalidArgumentException;

final class TenantCacheKey
{
    public function __construct(private readonly TenantContextStore $store) {}

    public function make(string $key): string
    {
        $key = trim($key);

        if ($key === '' || preg_match('/[\s:{}]/', $key) === 1) {
            throw new InvalidArgumentException('A safe tenant cache key is required.');
        }

        return "tenant:{$this->store->current()->tenant->id}:{$key}";
    }
}
