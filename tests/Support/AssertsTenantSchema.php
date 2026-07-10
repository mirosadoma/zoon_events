<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;

trait AssertsTenantSchema
{
    /**
     * @param  list<string>  $tables
     */
    protected function assertTenantOwnedTablesRequireTenantId(array $tables): void
    {
        $schema = (string) config('database.connections.mysql.database');

        foreach ($tables as $table) {
            $column = DB::selectOne(
                'SELECT IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$schema, $table, 'tenant_id'],
            );

            Assert::assertNotNull($column, "Expected {$table}.tenant_id to exist.");
            Assert::assertSame('NO', $column->IS_NULLABLE, "{$table}.tenant_id must be NOT NULL.");
        }
    }

    protected function assertUniqueConstraintStartsWithTenantId(string $table, string $indexName): void
    {
        $schema = (string) config('database.connections.mysql.database');

        $columns = DB::select(
            'SELECT COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
             ORDER BY SEQ_IN_INDEX',
            [$schema, $table, $indexName],
        );

        Assert::assertNotEmpty($columns, "Expected unique/index {$indexName} on {$table}.");
        Assert::assertSame('tenant_id', $columns[0]->COLUMN_NAME, "{$indexName} on {$table} must be tenant-first.");
    }

    protected function assertCheckConstraintExists(string $table, string $constraintName): void
    {
        $schema = (string) config('database.connections.mysql.database');

        $constraint = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$schema, $table, $constraintName, 'CHECK'],
        );

        Assert::assertNotNull($constraint, "Expected CHECK constraint {$constraintName} on {$table}.");
    }

    protected function assertIndexExists(string $table, string $indexName): void
    {
        $schema = (string) config('database.connections.mysql.database');

        $index = DB::selectOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
             LIMIT 1',
            [$schema, $table, $indexName],
        );

        Assert::assertNotNull($index, "Expected index {$indexName} on {$table}.");
    }
}
