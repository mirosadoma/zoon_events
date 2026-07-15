<?php

namespace Tests\Feature\VenueMarketplace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VenueCatalogSchemaTest extends TestCase
{
    public function test_us1_schema_matches_the_private_source_and_public_projection_contract(): void
    {
        foreach ([
            'venues',
            'venue_assets',
            'venue_asset_bindings',
            'asset_availability_windows',
            'marketplace_catalog_publications',
            'marketplace_publication_capabilities',
        ] as $table) {
            self::assertTrue(Schema::hasTable($table), "Missing {$table} table.");
            self::assertTrue(Schema::hasColumn($table, 'tenant_id'));
            self::assertFalse(Schema::hasColumn($table, 'deleted_at'), "{$table} must use lifecycle retention, not soft deletes.");
        }

        $this->assertColumns('venues', [
            'public_id', 'name_en', 'name_ar', 'description_en', 'description_ar',
            'address_en', 'address_ar', 'country_code', 'city_code', 'timezone',
            'business_contact_name', 'business_contact_email', 'business_contact_phone',
            'publish_contact', 'status', 'version', 'activated_at', 'suspended_at',
            'archived_at', 'created_by_user_id', 'updated_by_user_id', 'created_at', 'updated_at',
        ]);
        $this->assertColumns('venue_assets', [
            'venue_id', 'public_id', 'asset_type', 'name_en', 'name_ar', 'location_en',
            'location_ar', 'capabilities', 'capacity_per_minute', 'operational_status',
            'pricing_model', 'price_minor', 'currency', 'version', 'retired_at',
        ]);
        $this->assertColumns('venue_asset_bindings', [
            'venue_asset_id', 'control_family', 'adapter_key', 'opaque_reference',
            'binding_metadata', 'status', 'verified_at', 'disabled_at',
        ]);
        $this->assertColumns('asset_availability_windows', [
            'venue_asset_id', 'public_id', 'available_from', 'available_until',
            'local_from', 'local_until', 'source_timezone', 'status', 'reason_code', 'version',
        ]);
        $this->assertColumns('marketplace_catalog_publications', [
            'public_id', 'venue_id', 'venue_asset_id', 'venue_public_id', 'asset_public_id',
            'publication_version', 'venue_version', 'asset_version', 'venue_name_en',
            'venue_name_ar', 'asset_name_en', 'asset_name_ar', 'country_code', 'city_code',
            'timezone', 'asset_type', 'capacity_per_minute', 'pricing_model', 'price_minor',
            'currency', 'public_contact', 'status', 'published_at', 'withdrawn_at',
        ]);

        foreach (['venues', 'venue_assets', 'asset_availability_windows', 'marketplace_catalog_publications'] as $table) {
            self::assertTrue($this->hasUniqueIndex($table, ['public_id']), "{$table}.public_id must be unique.");
        }

        self::assertTrue($this->hasUniqueIndex('venues', ['tenant_id', 'id']));
        self::assertTrue($this->hasUniqueIndex('venue_assets', ['tenant_id', 'venue_id', 'id']));
        self::assertTrue($this->hasUniqueIndex(
            'marketplace_catalog_publications',
            ['tenant_id', 'venue_asset_id', 'publication_version'],
        ));
    }

    public function test_us1_migrations_are_reversible(): void
    {
        foreach (range(2, 5) as $sequence) {
            $path = database_path(sprintf('migrations/2026_07_14_%06d_', $sequence));
            $file = collect(glob($path.'*.php'))->sole();
            $source = file_get_contents($file);

            self::assertStringContainsString('public function down(): void', $source);
            self::assertStringContainsString('Schema::dropIfExists', $source);
        }
    }

    public function test_us3_schema_matches_reservation_and_delegation_contracts(): void
    {
        foreach (['asset_reservations', 'control_delegations', 'delegated_asset_resources'] as $table) {
            self::assertTrue(Schema::hasTable($table), "Missing {$table} table.");
            self::assertTrue(Schema::hasColumn($table, 'tenant_id'));
            self::assertTrue(Schema::hasColumn($table, 'organizer_tenant_id'));
            self::assertFalse(Schema::hasColumn($table, 'deleted_at'), "{$table} must preserve lifecycle history without soft deletes.");
        }

        $this->assertColumns('asset_reservations', [
            'rental_request_id', 'rental_asset_id', 'venue_asset_id', 'reserved_from',
            'reserved_until', 'status', 'release_reason_code', 'activated_at',
            'completed_at', 'released_at', 'created_at', 'updated_at',
        ]);
        $this->assertColumns('control_delegations', [
            'public_id', 'rental_request_id', 'event_id', 'status', 'starts_at', 'ends_at',
            'revoked_at', 'expired_at', 'completed_at', 'revoked_by_user_id',
            'revocation_reason', 'degraded_reason_code', 'provision_attempts',
            'last_provision_attempt_at', 'version', 'idempotency_key_hash', 'created_at', 'updated_at',
        ]);
        $this->assertColumns('delegated_asset_resources', [
            'control_delegation_id', 'rental_asset_id', 'venue_asset_id', 'resource_module',
            'resource_type', 'resource_public_reference', 'granted_capabilities',
            'provisioning_status', 'failure_reason_code', 'provisioned_at', 'released_at',
            'idempotency_key_hash', 'created_at', 'updated_at',
        ]);

        self::assertTrue($this->hasUniqueIndex('asset_reservations', ['tenant_id', 'rental_asset_id']));
        self::assertTrue($this->hasIndex(
            'asset_reservations',
            ['tenant_id', 'venue_asset_id', 'status', 'reserved_from', 'reserved_until'],
        ));
        self::assertTrue($this->hasUniqueIndex('control_delegations', ['public_id']));
        self::assertTrue($this->hasUniqueIndex(
            'control_delegations',
            ['tenant_id', 'organizer_tenant_id', 'rental_request_id'],
        ));
        self::assertTrue($this->hasUniqueIndex(
            'delegated_asset_resources',
            ['tenant_id', 'organizer_tenant_id', 'control_delegation_id', 'rental_asset_id'],
        ));
    }

    public function test_us3_migrations_are_reversible_and_preserve_composite_integrity(): void
    {
        foreach ([8, 9] as $sequence) {
            $path = database_path(sprintf('migrations/2026_07_14_%06d_', $sequence));
            $files = glob($path.'*.php');

            if ($files === []) {
                self::markTestSkipped("US3 migration {$sequence} is not present yet.");
            }

            $source = file_get_contents(collect($files)->sole());

            self::assertStringContainsString('public function down(): void', $source);
            self::assertStringContainsString('Schema::dropIfExists', $source);
            self::assertStringContainsString('tenant_id', $source);
            self::assertStringContainsString('organizer_tenant_id', $source);
            self::assertStringContainsString('rental_request_id', $source);
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

    public function test_us5_schema_matches_settlement_statement_and_dispute_contracts(): void
    {
        foreach ([
            'settlement_statements',
            'settlement_statement_lines',
            'marketplace_disputes',
            'marketplace_dispute_events',
        ] as $table) {
            self::assertTrue(Schema::hasTable($table), "Missing {$table} table.");
            self::assertTrue(Schema::hasColumn($table, 'tenant_id'));
            self::assertTrue(Schema::hasColumn($table, 'organizer_tenant_id'));
            self::assertFalse(Schema::hasColumn($table, 'deleted_at'), "{$table} must preserve immutable history without soft deletes.");
        }

        $this->assertColumns('settlement_statements', [
            'public_id', 'rental_request_id', 'statement_number', 'revision',
            'supersedes_statement_id', 'status', 'dispute_status', 'rental_outcome',
            'venue_timezone', 'agreed_start_at', 'agreed_end_at', 'currency',
            'agreed_total_minor', 'issued_at', 'generated_by', 'created_at',
        ]);
        $this->assertColumns('settlement_statement_lines', [
            'settlement_statement_id', 'rental_asset_id', 'publication_public_id',
            'publication_version', 'asset_public_id', 'asset_type', 'name_en', 'name_ar',
            'pricing_model', 'unit_price_minor', 'billable_units', 'line_total_minor', 'currency',
            'created_at',
        ]);
        $this->assertColumns('marketplace_disputes', [
            'public_id', 'rental_request_id', 'settlement_statement_id',
            'reported_by_tenant_id', 'reported_by_user_id', 'status', 'reason_code',
            'reason', 'assigned_platform_user_id', 'resolution_code', 'resolution_summary',
            'opened_at', 'review_started_at', 'resolved_at',
        ]);
        $this->assertColumns('marketplace_dispute_events', [
            'marketplace_dispute_id', 'event_type', 'actor_scope', 'actor_user_id',
            'visibility', 'reason_code', 'note', 'created_at',
        ]);

        self::assertTrue($this->hasUniqueIndex('settlement_statements', ['public_id']));
        self::assertTrue($this->hasUniqueIndex(
            'settlement_statements',
            ['tenant_id', 'organizer_tenant_id', 'id'],
        ));
        self::assertTrue($this->hasUniqueIndex(
            'settlement_statements',
            ['tenant_id', 'organizer_tenant_id', 'rental_request_id', 'revision'],
        ));
        self::assertTrue($this->hasUniqueIndex(
            'settlement_statement_lines',
            ['tenant_id', 'organizer_tenant_id', 'settlement_statement_id', 'rental_asset_id'],
        ));
        self::assertTrue($this->hasUniqueIndex('marketplace_disputes', ['public_id']));
        self::assertTrue($this->hasUniqueIndex(
            'marketplace_disputes',
            ['tenant_id', 'organizer_tenant_id', 'id'],
        ));
        self::assertTrue($this->hasIndex(
            'marketplace_disputes',
            ['status', 'opened_at', 'id'],
        ));
        self::assertTrue($this->hasIndex(
            'marketplace_disputes',
            ['tenant_id', 'organizer_tenant_id', 'settlement_statement_id', 'status'],
        ));
        self::assertTrue($this->hasIndex(
            'marketplace_dispute_events',
            ['tenant_id', 'organizer_tenant_id', 'marketplace_dispute_id', 'created_at', 'id'],
        ));

        foreach (['settlement_statements', 'settlement_statement_lines'] as $table) {
            self::assertFalse(
                Schema::hasColumn($table, 'payment_minor'),
                "{$table} must not contain payment fields.",
            );
            self::assertFalse(
                Schema::hasColumn($table, 'payout_minor'),
                "{$table} must not contain payout fields.",
            );
            self::assertFalse(
                Schema::hasColumn($table, 'refund_minor'),
                "{$table} must not contain refund fields.",
            );
            self::assertFalse(
                Schema::hasColumn($table, 'penalty_minor'),
                "{$table} must not contain penalty fields.",
            );
            self::assertFalse(
                Schema::hasColumn($table, 'vat_minor'),
                "{$table} must not contain VAT fields.",
            );
            self::assertFalse(
                Schema::hasColumn($table, 'tax_minor'),
                "{$table} must not contain tax fields.",
            );
        }
    }

    public function test_us5_settlement_statements_have_no_updated_at_column(): void
    {
        self::assertFalse(
            Schema::hasColumn('settlement_statements', 'updated_at'),
            'settlement_statements must be immutable revisions — no updated_at.',
        );
        self::assertFalse(
            Schema::hasColumn('settlement_statement_lines', 'updated_at'),
            'settlement_statement_lines must be immutable — no updated_at.',
        );
        self::assertFalse(
            Schema::hasColumn('marketplace_dispute_events', 'updated_at'),
            'marketplace_dispute_events must be append-only — no updated_at.',
        );
    }

    public function test_us5_one_open_dispute_per_statement_enforced_by_generated_column(): void
    {
        self::assertTrue(
            Schema::hasColumn('marketplace_disputes', 'active_statement_id'),
            'marketplace_disputes must have a generated active_statement_id column for one-active constraint.',
        );
        self::assertTrue(
            $this->hasUniqueIndex('marketplace_disputes', ['tenant_id', 'organizer_tenant_id', 'active_statement_id']),
            'marketplace_disputes must enforce one active dispute per statement via unique index.',
        );
    }

    public function test_us5_migrations_are_reversible(): void
    {
        foreach ([14, 15] as $sequence) {
            $path = database_path(sprintf('migrations/2026_07_14_%06d_', $sequence));
            $files = glob($path.'*.php');

            if ($files === []) {
                self::markTestSkipped("US5 migration {$sequence} is not present yet.");
            }

            $source = file_get_contents(collect($files)->sole());

            self::assertStringContainsString('public function down(): void', $source);
            self::assertStringContainsString('Schema::dropIfExists', $source);
            self::assertStringContainsString('tenant_id', $source);
            self::assertStringContainsString('organizer_tenant_id', $source);
        }
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
