<?php

namespace Tests\Feature\VenueMarketplace;

use Tests\TestCase;

final class StatementFinancialBoundaryTest extends TestCase
{
    private const SCANNED_PATHS = [
        'app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/SettlementStatement.php',
        'app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/SettlementStatementLine.php',
        'app/Modules/VenueMarketplace/Application/Actions/GenerateSettlementStatementAction.php',
        'app/Modules/VenueMarketplace/Application/Actions/ReviseSettlementStatementAction.php',
        'app/Modules/VenueMarketplace/Application/Exports/StreamSettlementStatementCsv.php',
        'app/Modules/VenueMarketplace/Http/Resources/ParticipantStatementResource.php',
        'app/Modules/VenueMarketplace/Domain/Services/SettlementRevisionPolicy.php',
        'database/migrations/2026_07_14_000014_create_settlement_statements_tables.php',
    ];

    private const FORBIDDEN_TERMS = [
        'payment',
        'payout',
        'refund',
        'penalty',
        'tax_minor',
        'vat_minor',
        'tax_rate',
        'vat_rate',
        'accounting_entry',
        'ledger',
        'invoice_number',
        'receipt',
        'charge',
        'debit',
        'credit',
        'bank_transfer',
        'wire_transfer',
    ];

    public function test_settlement_schema_contains_no_fund_movement_or_tax_columns(): void
    {
        $source = file_get_contents(base_path(
            'database/migrations/2026_07_14_000014_create_settlement_statements_tables.php',
        ));

        foreach (self::FORBIDDEN_TERMS as $term) {
            self::assertStringNotContainsString(
                $term,
                strtolower($source),
                "Migration must not contain '{$term}' — statements record agreed facts only.",
            );
        }
    }

    public function test_statement_resource_declares_funds_moved_false(): void
    {
        $source = file_get_contents(base_path(
            'app/Modules/VenueMarketplace/Http/Resources/ParticipantStatementResource.php',
        ));

        self::assertStringContainsString("'funds_moved' => false", $source);
    }

    public function test_csv_export_contains_no_fund_movement_terminology(): void
    {
        $source = file_get_contents(base_path(
            'app/Modules/VenueMarketplace/Application/Exports/StreamSettlementStatementCsv.php',
        ));

        foreach (self::FORBIDDEN_TERMS as $term) {
            self::assertStringNotContainsString(
                $term,
                strtolower($source),
                "CSV export must not reference '{$term}'.",
            );
        }
    }

    public function test_all_scanned_sources_are_free_of_financial_accounting_claims(): void
    {
        foreach (self::SCANNED_PATHS as $path) {
            $fullPath = base_path($path);
            if (! file_exists($fullPath)) {
                self::markTestSkipped("Source file {$path} not found.");
            }

            $source = strtolower(file_get_contents($fullPath));

            foreach (['payment', 'payout', 'refund', 'penalty'] as $term) {
                self::assertStringNotContainsString(
                    $term,
                    $source,
                    "File {$path} must not reference '{$term}' — statements record facts only.",
                );
            }
        }
    }
}
