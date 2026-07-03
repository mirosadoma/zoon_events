<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Application\Queries\ListTenantConfiguration;
use App\Modules\Tenancy\Domain\Configuration\ConfigurationSchemaRegistry;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantConfiguration;

class ConfigurationController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly ConfigurationSchemaRegistry $schemas,
        private readonly ListTenantConfiguration $configurations,
    ) {}

    public function platformSchemas()
    {
        return $this->success(
            collect($this->schemas->definitions())
                ->map(fn (array $definition, string $key): array => ['key' => $key] + $definition)
                ->values()
                ->all(),
        );
    }

    public function tenantConfigurations()
    {
        $configs = $this->configurations->handle()
            ->map(fn (TenantConfiguration $configuration): array => [
                'key' => $configuration->key,
                'schema_version' => $configuration->schema_version,
                'status' => $configuration->status,
                'value' => $configuration->value,
            ]);

        return $this->success($configs->values()->all());
    }
}
