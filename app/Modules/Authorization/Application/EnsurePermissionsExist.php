<?php

namespace App\Modules\Authorization\Application;

use App\Modules\Authorization\Domain\PermissionCatalog;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use InvalidArgumentException;

/**
 * Upserts catalog-defined permission rows so role assignment can proceed
 * even when a newly added catalog key has not been seeded yet.
 */
final class EnsurePermissionsExist
{
    /**
     * @param  list<string>  $keys
     * @return list<int|string>
     */
    public function forScope(string $scope, array $keys): array
    {
        if (! in_array($scope, ['platform', 'tenant'], true)) {
            throw new InvalidArgumentException("Unsupported permission scope [{$scope}].");
        }

        $catalogByKey = collect(
            $scope === 'platform' ? PermissionCatalog::platform() : PermissionCatalog::tenant()
        )->keyBy('key');

        $ids = [];

        foreach (array_values(array_unique($keys)) as $key) {
            /** @var array{key: string, module: string, description: string, scope: string, risk_level: string}|null $definition */
            $definition = $catalogByKey->get($key);

            if ($definition === null) {
                throw new InvalidArgumentException("Permission [{$key}] is not defined for scope [{$scope}].");
            }

            $permission = Permission::query()->updateOrCreate(
                ['key' => $key],
                [
                    'module' => $definition['module'],
                    'description' => $definition['description'],
                    'scope' => $definition['scope'],
                    'risk_level' => $definition['risk_level'],
                ],
            );

            $ids[] = $permission->id;
        }

        return $ids;
    }
}
