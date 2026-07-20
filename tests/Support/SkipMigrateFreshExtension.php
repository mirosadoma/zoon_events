<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * PHPUnit extension that marks the test database as already migrated,
 * preventing RefreshDatabase from running `migrate:fresh` on each suite.
 *
 * This works around a MySQL 8.4 InnoDB dictionary corruption bug where
 * dropping and recreating all tables in rapid succession causes "table
 * already exists" or "table not found" errors.
 *
 * Pre-requisite: run `php premigrate3.php` once before the test suite
 * to populate the zonetec_testing database.
 */
final class SkipMigrateFreshExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        RefreshDatabaseState::$migrated = true;
    }
}
