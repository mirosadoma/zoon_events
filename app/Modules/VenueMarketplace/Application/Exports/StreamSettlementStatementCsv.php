<?php

namespace App\Modules\VenueMarketplace\Application\Exports;

use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Audit\DatabaseMarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class StreamSettlementStatementCsv
{
    public function __construct(private DatabaseMarketplaceAuditWriter $audit) {}

    public function execute(
        int $actorTenantId,
        int $actorUserId,
        string $statementPublicId,
        string $correlationId,
        string $locale = 'en',
    ): StreamedResponse {
        $statement = SettlementStatement::query()
            ->forParticipantPublicId($actorTenantId, $statementPublicId)
            ->with('lines')
            ->first();

        if ($statement === null) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_STATEMENT_NOT_FOUND);
        }

        $this->writeExportAudit($statement, $actorTenantId, $actorUserId, $correlationId);

        $filename = "statement-{$statement->statement_number}.csv";

        return new StreamedResponse(
            function () use ($statement, $locale): void {
                $handle = fopen('php://output', 'w');

                fwrite($handle, "\xEF\xBB\xBF");

                $headers = $locale === 'ar'
                    ? ['المعرف العام', 'نوع الأصل', 'الاسم', 'نموذج التسعير', 'سعر الوحدة', 'الوحدات', 'الإجمالي', 'العملة']
                    : ['Asset Public ID', 'Asset Type', 'Name', 'Pricing Model', 'Unit Price', 'Billable Units', 'Line Total', 'Currency'];

                fputcsv($handle, $headers);

                foreach ($statement->lines as $line) {
                    $name = $locale === 'ar' ? $line->name_ar : $line->name_en;

                    fputcsv($handle, [
                        $this->escapeFormula($line->asset_public_id),
                        $this->escapeFormula($line->asset_type),
                        $this->escapeFormula($name),
                        $this->escapeFormula($line->pricing_model),
                        (int) $line->unit_price_minor,
                        (int) $line->billable_units,
                        (int) $line->line_total_minor,
                        $line->currency,
                    ]);
                }

                fclose($handle);
            },
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'no-store, no-cache',
            ],
        );
    }

    private function escapeFormula(string $value): string
    {
        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'{$value}";
        }

        return $value;
    }

    private function writeExportAudit(
        SettlementStatement $statement,
        int $actorTenantId,
        int $actorUserId,
        string $correlationId,
    ): void {
        $scope = (int) $statement->tenant_id === $actorTenantId ? 'owner' : 'organizer';

        $this->audit->write(new MarketplaceAuditEvent(
            'statement.exported',
            $scope,
            'succeeded',
            $correlationId,
            $statement->public_id,
            [
                'revision' => (int) $statement->revision,
                'format' => 'csv',
            ],
            ownerTenantId: (int) $statement->tenant_id,
            organizerTenantId: (int) $statement->organizer_tenant_id,
            actorUserId: $actorUserId,
        ));
    }
}
