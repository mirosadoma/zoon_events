<?php

namespace Tests\Unit\VenueMarketplace;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\SettlementRevisionPolicy;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SettlementStatementImmutabilityTest extends TestCase
{
    private SettlementRevisionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new SettlementRevisionPolicy;
    }

    public function test_revision_chain_increments_sequentially(): void
    {
        self::assertSame(2, $this->policy->nextRevision(1));
        self::assertSame(5, $this->policy->nextRevision(4));
        self::assertSame(100, $this->policy->nextRevision(99));
    }

    public function test_only_issued_statements_can_be_revised(): void
    {
        $this->policy->assertCanRevise('issued', 1);
        self::assertTrue(true);
    }

    #[DataProvider('nonIssuedStatuses')]
    public function test_non_issued_statuses_reject_revision(string $status): void
    {
        try {
            $this->policy->assertCanRevise($status, 1);
            self::fail('Expected non-issued status to prevent revision.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_STATEMENT_NOT_READY, $exception->reasonCode);
        }
    }

    public static function nonIssuedStatuses(): array
    {
        return [
            'superseded' => ['superseded'],
            'unknown' => ['draft'],
        ];
    }

    public function test_compute_total_is_exact_integer_sum_of_line_products(): void
    {
        $lines = [
            ['unit_price_minor' => 1000, 'billable_units' => 2],
            ['unit_price_minor' => 500, 'billable_units' => 3],
            ['unit_price_minor' => 2500, 'billable_units' => 1],
        ];

        self::assertSame(1000 * 2 + 500 * 3 + 2500 * 1, $this->policy->computeTotal($lines));
    }

    public function test_compute_total_with_zero_price_returns_zero(): void
    {
        $lines = [
            ['unit_price_minor' => 0, 'billable_units' => 10],
        ];

        self::assertSame(0, $this->policy->computeTotal($lines));
    }

    public function test_negative_line_total_is_rejected(): void
    {
        try {
            $this->policy->computeTotal([
                ['unit_price_minor' => -100, 'billable_units' => 1],
            ]);
            self::fail('Expected negative line total to be denied.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_STATEMENT_NOT_READY, $exception->reasonCode);
        }
    }

    public function test_compute_total_returns_integer_not_float(): void
    {
        $total = $this->policy->computeTotal([
            ['unit_price_minor' => 333, 'billable_units' => 3],
        ]);

        self::assertIsInt($total);
        self::assertSame(999, $total);
    }

    public function test_empty_line_set_returns_zero_total(): void
    {
        self::assertSame(0, $this->policy->computeTotal([]));
    }

    public function test_reason_code_is_required_and_bounded(): void
    {
        $this->policy->assertReasonCode('factual_correction');
        self::assertTrue(true);

        foreach ([
            '',
            '   ',
            str_repeat('x', 81),
        ] as $invalid) {
            try {
                $this->policy->assertReasonCode($invalid);
                self::fail("Expected reason code '{$invalid}' to be rejected.");
            } catch (MarketplaceDomainException $exception) {
                self::assertSame(Phase6Problem::MARKETPLACE_STATEMENT_NOT_READY, $exception->reasonCode);
            }
        }
    }

    public function test_dispute_link_requires_actionable_status(): void
    {
        foreach (['open', 'under_review', 'resolved'] as $valid) {
            $this->policy->assertDisputeLinked($valid);
        }

        try {
            $this->policy->assertDisputeLinked('rejected');
            self::fail('Expected rejected dispute to block revision.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_DISPUTE_STATE_CONFLICT, $exception->reasonCode);
        }
    }

    public function test_no_payment_payout_refund_penalty_vat_fields_on_policy(): void
    {
        $reflection = new \ReflectionClass(SettlementRevisionPolicy::class);
        $source = file_get_contents($reflection->getFileName());

        foreach (['payment', 'payout', 'refund', 'penalty', 'vat', 'tax'] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                strtolower($source),
                "SettlementRevisionPolicy must not reference {$forbidden} — statements record facts only.",
            );
        }
    }
}
