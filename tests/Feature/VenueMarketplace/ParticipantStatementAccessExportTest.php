<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\CancelRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\GenerateSettlementStatementAction;
use App\Modules\VenueMarketplace\Application\Exports\StreamSettlementStatementCsv;
use App\Modules\VenueMarketplace\Application\Queries\GetParticipantStatementQuery;
use App\Modules\VenueMarketplace\Application\Queries\ListParticipantStatementsQuery;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class ParticipantStatementAccessExportTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_owner_and_organizer_can_list_their_statements(): void
    {
        [$owner, $organizer, $statement] = $this->issuedStatement('list-access');

        $ownerPage = app(ListParticipantStatementsQuery::class)
            ->execute((int) $owner['tenant']->id);
        self::assertCount(1, $ownerPage->items);

        $organizerPage = app(ListParticipantStatementsQuery::class)
            ->execute((int) $organizer['tenant']->id);
        self::assertCount(1, $organizerPage->items);
    }

    public function test_owner_and_organizer_can_read_same_statement_by_public_id(): void
    {
        [$owner, $organizer, $statement] = $this->issuedStatement('read-access');

        $ownerResult = app(GetParticipantStatementQuery::class)
            ->execute((int) $owner['tenant']->id, $statement->public_id);
        self::assertSame($statement->public_id, $ownerResult->public_id);

        $organizerResult = app(GetParticipantStatementQuery::class)
            ->execute((int) $organizer['tenant']->id, $statement->public_id);
        self::assertSame($statement->public_id, $organizerResult->public_id);

        self::assertSame(
            (int) $ownerResult->agreed_total_minor,
            (int) $organizerResult->agreed_total_minor,
        );
    }

    public function test_unrelated_tenant_cannot_access_statement(): void
    {
        [$owner, $organizer, $statement] = $this->issuedStatement('unrelated-access');
        $unrelated = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);

        try {
            app(GetParticipantStatementQuery::class)
                ->execute((int) $unrelated['tenant']->id, $statement->public_id);
            self::fail('Expected unrelated tenant to be denied.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_STATEMENT_NOT_FOUND, $exception->reasonCode);
        }
    }

    public function test_statement_hides_internal_ids_from_participant_resource(): void
    {
        [$owner, , $statement] = $this->issuedStatement('opaque-ids');

        $result = app(GetParticipantStatementQuery::class)
            ->execute((int) $owner['tenant']->id, $statement->public_id);

        $hidden = $result->getHidden();
        self::assertContains('id', $hidden);
        self::assertContains('tenant_id', $hidden);
        self::assertContains('organizer_tenant_id', $hidden);
        self::assertContains('rental_request_id', $hidden);
        self::assertContains('supersedes_statement_id', $hidden);
    }

    public function test_csv_export_streams_with_utf8_bom_and_formula_escaping(): void
    {
        [$owner, , $statement] = $this->issuedStatement('csv-export');

        $response = app(StreamSettlementStatementCsv::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $statement->public_id,
            'csv-export-correlation',
            'en',
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        self::assertStringStartsWith("\xEF\xBB\xBF", $output, 'CSV must start with UTF-8 BOM.');
        self::assertStringContainsString('Asset Public ID', $output);
        self::assertStringContainsString('Line Total', $output);
    }

    public function test_csv_export_arabic_locale_uses_arabic_headers(): void
    {
        [$owner, , $statement] = $this->issuedStatement('csv-arabic');

        $response = app(StreamSettlementStatementCsv::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $statement->public_id,
            'csv-arabic-correlation',
            'ar',
        );

        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        self::assertStringContainsString('المعرف العام', $output);
        self::assertStringContainsString('العملة', $output);
    }

    public function test_csv_formula_injection_is_escaped(): void
    {
        $exporter = app(StreamSettlementStatementCsv::class);
        $reflection = new \ReflectionMethod($exporter, 'escapeFormula');

        self::assertSame("'=HYPERLINK()", $reflection->invoke($exporter, '=HYPERLINK()'));
        self::assertSame("'+cmd|'", $reflection->invoke($exporter, '+cmd|\''));
        self::assertSame("'-exec", $reflection->invoke($exporter, '-exec'));
        self::assertSame("'@sum", $reflection->invoke($exporter, '@sum'));
        self::assertSame('safe_value', $reflection->invoke($exporter, 'safe_value'));
    }

    /**
     * @return array{0:array,1:array,2:SettlementStatement}
     */
    private function issuedStatement(string $key): array
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, $key);
        app(TenantContextStore::class)->clear();
        $pubId = $inventory['assets'][3]->publications()->where('status', 'active')->value('public_id');
        $rental = $this->createSubmittedMarketplaceRental($organizer, $event, [$pubId], "{$key}-rental");

        $rental = app(CancelRentalAction::class)->execute(
            (int) $organizer['tenant']->id,
            (int) $organizer['user']->id,
            $rental->public_id,
            1,
            "{$key}-cancel-correlation",
        );

        $statement = app(GenerateSettlementStatementAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->public_id,
            "{$key}-gen-correlation",
        );

        return [$owner, $organizer, $statement];
    }
}
