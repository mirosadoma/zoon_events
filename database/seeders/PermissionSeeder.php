<?php

namespace Database\Seeders;

use App\Modules\Authorization\Domain\PermissionCatalog;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * @return array<int, array{key: string, module: string, description: string, scope: string, risk_level: string}>
     */
    public static function definitions(): array
    {
        return PermissionCatalog::all();
    }

    public function run(): void
    {
        foreach (PermissionCatalog::all() as $definition) {
            Permission::query()->updateOrCreate(
                ['key' => $definition['key']],
                $definition,
            );
        }
    }
}
