<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionCatalogSeeder::class);
        $this->call(PlatformSeeder::class);
        $this->call(SystemRoleSeeder::class);
        $this->call(FoundationIsolationSeeder::class);
        $this->call(Phase1RegistrationSeeder::class);
    }
}
