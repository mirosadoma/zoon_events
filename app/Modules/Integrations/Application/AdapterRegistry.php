<?php

namespace App\Modules\Integrations\Application;

use App\Modules\Integrations\Contracts\CapabilityAdapter;
use RuntimeException;

class AdapterRegistry
{
    /**
     * @param  iterable<CapabilityAdapter>  $adapters
     */
    public function __construct(
        private readonly iterable $adapters,
    ) {}

    public function for(string $capability, ?string $adapterKey = null): CapabilityAdapter
    {
        foreach ($this->adapters as $adapter) {
            $descriptor = $adapter->descriptor();

            if ($descriptor->capability !== $capability) {
                continue;
            }

            if ($adapterKey !== null && $descriptor->key !== $adapterKey) {
                continue;
            }

            if (app()->environment('production') && $descriptor->testingOnly) {
                throw new RuntimeException('A testing-only adapter cannot be selected in production.');
            }

            return $adapter;
        }

        throw new RuntimeException('No approved adapter is registered for the requested capability.');
    }

    public function productionReady(string $capability): bool
    {
        try {
            return ! $this->for($capability)->descriptor()->testingOnly;
        } catch (RuntimeException) {
            return false;
        }
    }
}
