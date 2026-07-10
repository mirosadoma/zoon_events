<?php

namespace Tests\Contract;

use Database\Seeders\PermissionSeeder;
use Tests\TestCase;

class DocumentationCompletenessTest extends TestCase
{
    public function test_documentation_gate_and_permission_catalog_are_current(): void
    {
        $this->artisan('zonetec:docs:check')->assertSuccessful();
        $catalog = file_get_contents(base_path('docs/standards/permission-catalog.md'));
        foreach (PermissionSeeder::definitions() as $definition) {
            self::assertStringContainsString($definition['key'], $catalog);
        }
    }
}
