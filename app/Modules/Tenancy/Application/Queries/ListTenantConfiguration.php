<?php

namespace App\Modules\Tenancy\Application\Queries;

use App\Modules\Tenancy\Domain\Configuration\ConfigurationSchemaRegistry;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantConfiguration;
use Illuminate\Support\Collection;

final class ListTenantConfiguration
{
    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly ConfigurationSchemaRegistry $schemas,
    ) {}

    public function handle(): Collection
    {
        return TenantConfiguration::query()
            ->where('tenant_id', $this->contexts->current()->tenant->id)
            ->where('status', 'active')
            ->orderBy('key')
            ->get()
            ->filter(function (TenantConfiguration $configuration): bool {
                try {
                    $this->schemas->validate($configuration->key, $configuration->value);

                    return true;
                } catch (\Throwable) {
                    return false;
                }
            })
            ->values();
    }
}
