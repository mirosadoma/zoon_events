<?php

namespace Tests\Integration\VenueMarketplace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase6MigrationRollbackTest extends TestCase
{
    use RefreshDatabase;

    private const PHASE_6_TABLES = [
        'settlement_statements',
        'settlement_statement_lines',
        'marketplace_disputes',
        'marketplace_dispute_events',
    ];

    private const PHASE_6_MIGRATION_SEQUENCES = [14, 15];

    public function test_phase6_tables_exist_after_migration(): void
    {
        foreach (self::PHASE_6_TABLES as $table) {
            self::assertTrue(Schema::hasTable($table), "Phase 6 table {$table} must exist after migration.");
        }
    }

    public function test_settlement_statements_schema_has_required_columns(): void
    {
        $this->assertColumns('settlement_statements', [
            'tenant_id', 'organizer_tenant_id', 'public_id', 'rental_request_id',
            'statement_number', 'revision', 'supersedes_statement_id', 'status',
            'dispute_status', 'rental_outcome', 'venue_timezone', 'agreed_start_at',
            'agreed_end_at', 'currency', 'agreed_total_minor', 'issued_at',
            'generated_by', 'created_at',
        ]);

        $this->assertColumns('settlement_statement_lines', [
            'tenant_id', 'organizer_tenant_id', 'settlement_statement_id',
            'rental_asset_id', 'publication_public_id', 'publication_version',
            'asset_public_id', 'asset_type', 'name_en', 'name_ar', 'pricing_model',
            'unit_price_minor', 'billable_units', 'line_total_minor', 'currency',
            'created_at',
        ]);
    }

    public function test_marketplace_disputes_schema_has_required_columns(): void
    {
        $this->assertColumns('marketplace_disputes', [
            'tenant_id', 'organizer_tenant_id', 'public_id', 'rental_request_id',
            'settlement_statement_id', 'reported_by_tenant_id', 'reported_by_user_id',
            'status', 'reason_code', 'reason', 'assigned_platform_user_id',
            'resolution_code', 'resolution_summary', 'opened_at', 'review_started_at',
            'resolved_at', 'created_at', 'updated_at',
        ]);

        $this->assertColumns('marketplace_dispute_events', [
            'tenant_id', 'organizer_tenant_id', 'marketplace_dispute_id',
            'event_type', 'actor_scope', 'actor_user_id', 'visibility',
            'reason_code', 'note', 'created_at',
        ]);
    }

    public function test_phase6_indexes_exist(): void
    {
        self::assertTrue(
            $this->hasUniqueIndex('settlement_statements', ['public_id']),
            'settlement_statements.public_id must be unique.',
        );
        self::assertTrue(
            $this->hasUniqueIndex('settlement_statements', ['statement_number']),
            'settlement_statements.statement_number must be unique.',
        );
        self::assertTrue(
            $this->hasUniqueIndex('settlement_statements', [
                'tenant_id', 'organizer_tenant_id', 'rental_request_id', 'revision',
            ]),
            'settlement_statements revision must be unique per rental per participant pair.',
        );
        self::assertTrue(
            $this->hasIndex('settlement_statements', [
                'tenant_id', 'status', 'created_at', 'id',
            ]),
            'settlement_statements owner index must exist.',
        );
        self::assertTrue(
            $this->hasIndex('settlement_statements', [
                'organizer_tenant_id', 'status', 'created_at', 'id',
            ]),
            'settlement_statements organizer index must exist.',
        );

        self::assertTrue(
            $this->hasUniqueIndex('marketplace_disputes', ['public_id']),
            'marketplace_disputes.public_id must be unique.',
        );
        self::assertTrue(
            $this->hasIndex('marketplace_disputes', ['status', 'opened_at', 'id']),
            'marketplace_disputes platform queue index must exist.',
        );
        self::assertTrue(
            $this->hasIndex('marketplace_dispute_events', [
                'tenant_id', 'organizer_tenant_id', 'marketplace_dispute_id', 'created_at', 'id',
            ]),
            'marketplace_dispute_events timeline index must exist.',
        );
    }

    public function test_phase6_foreign_keys_reference_tenants_and_rental_requests(): void
    {
        $database = DB::connection()->getDatabaseName();

        $fks = DB::select(
            'SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME IN (?, ?, ?, ?)',
            [$database, ...self::PHASE_6_TABLES],
        );

        $fkMap = collect($fks)->groupBy('TABLE_NAME');

        $statementRefs = $fkMap->get('settlement_statements', collect())->pluck('REFERENCED_TABLE_NAME')->unique()->sort()->values()->all();
        self::assertContains('tenants', $statementRefs, 'settlement_statements must FK to tenants.');
        self::assertContains('rental_requests', $statementRefs, 'settlement_statements must FK to rental_requests.');

        $disputeRefs = $fkMap->get('marketplace_disputes', collect())->pluck('REFERENCED_TABLE_NAME')->unique()->sort()->values()->all();
        self::assertContains('tenants', $disputeRefs, 'marketplace_disputes must FK to tenants.');
        self::assertContains('rental_requests', $disputeRefs, 'marketplace_disputes must FK to rental_requests.');
        self::assertContains('settlement_statements', $disputeRefs, 'marketplace_disputes must FK to settlement_statements.');
        self::assertContains('users', $disputeRefs, 'marketplace_disputes must FK to users.');

        $lineRefs = $fkMap->get('settlement_statement_lines', collect())->pluck('REFERENCED_TABLE_NAME')->unique()->values()->all();
        self::assertContains('settlement_statements', $lineRefs, 'settlement_statement_lines must FK to settlement_statements.');
    }

    public function test_phase6_rollback_on_empty_dataset_succeeds(): void
    {
        foreach (self::PHASE_6_TABLES as $table) {
            self::assertTrue(Schema::hasTable($table), "Pre-condition: {$table} must exist.");
            self::assertSame(0, DB::table($table)->count(), "Pre-condition: {$table} must be empty.");
        }

        foreach (array_reverse(self::PHASE_6_MIGRATION_SEQUENCES) as $sequence) {
            $path = database_path(sprintf('migrations/2026_07_14_%06d_', $sequence));
            $files = glob($path.'*.php');
            self::assertNotEmpty($files, "Migration {$sequence} file must exist.");

            $source = file_get_contents(collect($files)->sole());
            self::assertStringContainsString('public function down(): void', $source, "Migration {$sequence} must have a down() method.");
            self::assertStringContainsString('Schema::dropIfExists', $source, "Migration {$sequence} must use Schema::dropIfExists.");
        }

        $this->artisan('migrate:rollback', ['--step' => count(self::PHASE_6_MIGRATION_SEQUENCES)])
            ->assertSuccessful();

        foreach (self::PHASE_6_TABLES as $table) {
            self::assertFalse(Schema::hasTable($table), "{$table} must be dropped after rollback.");
        }
    }

    public function test_phase6_re_migration_after_rollback_succeeds(): void
    {
        $this->artisan('migrate:rollback', ['--step' => count(self::PHASE_6_MIGRATION_SEQUENCES)])
            ->assertSuccessful();

        foreach (self::PHASE_6_TABLES as $table) {
            self::assertFalse(Schema::hasTable($table));
        }

        $this->artisan('migrate')->assertSuccessful();

        foreach (self::PHASE_6_TABLES as $table) {
            self::assertTrue(Schema::hasTable($table), "{$table} must exist after re-migration.");
        }
    }

    public function test_existing_tenant_and_operational_records_survive_phase6_rollback(): void
    {
        $prePhaseTables = [
            'tenants', 'venues', 'venue_assets', 'venue_asset_bindings',
            'asset_availability_windows', 'marketplace_catalog_publications',
            'rental_requests', 'rental_assets', 'asset_reservations',
            'control_delegations', 'delegated_asset_resources',
        ];

        foreach ($prePhaseTables as $table) {
            if (! Schema::hasTable($table)) {
                self::markTestSkipped("Prerequisite table {$table} does not exist.");
            }
        }

        $this->artisan('migrate:rollback', ['--step' => count(self::PHASE_6_MIGRATION_SEQUENCES)])
            ->assertSuccessful();

        foreach ($prePhaseTables as $table) {
            self::assertTrue(Schema::hasTable($table), "Pre-Phase-6 table {$table} must survive rollback.");
        }

        $this->artisan('migrate')->assertSuccessful();
    }

    public function test_phase6_migrations_include_tenant_scoping(): void
    {
        foreach (self::PHASE_6_MIGRATION_SEQUENCES as $sequence) {
            $path = database_path(sprintf('migrations/2026_07_14_%06d_', $sequence));
            $source = file_get_contents(collect(glob($path.'*.php'))->sole());

            self::assertStringContainsString('tenant_id', $source, "Migration {$sequence} must include tenant_id scoping.");
            self::assertStringContainsString('organizer_tenant_id', $source, "Migration {$sequence} must include organizer_tenant_id scoping.");
        }
    }

    public function test_phase6_no_soft_deletes_on_settlement_or_dispute_tables(): void
    {
        foreach (self::PHASE_6_TABLES as $table) {
            self::assertFalse(
                Schema::hasColumn($table, 'deleted_at'),
                "{$table} must use lifecycle retention, not soft deletes.",
            );
        }
    }

    private function assertColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            self::assertTrue(Schema::hasColumn($table, $column), "Missing {$table}.{$column}.");
        }
    }

    private function hasUniqueIndex(string $table, array $columns): bool
    {
        return $this->hasIndex($table, $columns, true);
    }

    private function hasIndex(string $table, array $columns, ?bool $unique = null): bool
    {
        $database = DB::connection()->getDatabaseName();
        $rows = DB::select(
            'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$database, $table],
        );

        return collect($rows)
            ->groupBy('INDEX_NAME')
            ->contains(function ($index) use ($columns, $unique): bool {
                if ($index->pluck('COLUMN_NAME')->values()->all() !== $columns) {
                    return false;
                }

                return $unique === null || ((int) $index->first()->NON_UNIQUE === 0) === $unique;
            });
    }
}
