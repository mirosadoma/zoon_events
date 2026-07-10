<?php

namespace App\Modules\Tenancy\Application\Boundaries;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use InvalidArgumentException;

final class TenantStoragePath
{
    public function __construct(private readonly TenantContextStore $store) {}

    public function make(string $relativePath): string
    {
        $path = trim(str_replace('\\', '/', $relativePath), '/');

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, 'tenants/')) {
            throw new InvalidArgumentException('A safe tenant-relative storage path is required.');
        }

        return "tenants/{$this->store->current()->tenant->id}/{$path}";
    }
}
