<?php

namespace App\Modules\AdminConsole\ViewModels\Admin;

use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantConfiguration;
use Illuminate\Support\Collection;

final readonly class TenantSettingsViewModel
{
    /**
     * @param  Collection<int, TenantConfiguration>  $configurations
     * @return array{tenant: array<string, mixed>, tenantId: string, configurations: list<array<string, mixed>>}
     */
    public function index(Tenant $tenant, string $tenantId, Collection $configurations): array
    {
        return [
            'tenantId' => $tenantId,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'default_locale' => $tenant->default_locale,
                'timezone' => $tenant->timezone,
                'status' => $tenant->status->value,
            ],
            'configurations' => $configurations->map(fn (TenantConfiguration $configuration): array => [
                'key' => $configuration->key,
                'schema_version' => $configuration->schema_version,
                'status' => $configuration->status,
                'value' => $configuration->value,
            ])->values()->all(),
        ];
    }
}
