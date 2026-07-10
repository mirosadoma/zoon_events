<?php

namespace Tests\Unit\Ticketing;

use App\Modules\Ticketing\Application\Pricing\PriceTierEvaluator;
use App\Modules\Ticketing\Domain\ValueObjects\Money;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-1')]
#[Group('price-tiers')]
final class PriceTierEvaluatorTest extends TestCase
{
    public function test_time_windows_are_half_open_and_priority_is_deterministic(): void
    {
        $tiers = [
            (object) ['id' => 'late', 'price_minor' => 800, 'currency' => 'SAR', 'starts_at' => '2027-01-01T10:00:00Z', 'ends_at' => '2027-01-01T11:00:00Z', 'remaining_at_most' => null, 'priority' => 20, 'status' => 'active'],
            (object) ['id' => 'preferred', 'price_minor' => 700, 'currency' => 'SAR', 'starts_at' => '2027-01-01T10:00:00Z', 'ends_at' => '2027-01-01T11:00:00Z', 'remaining_at_most' => null, 'priority' => 10, 'status' => 'active'],
        ];
        $evaluator = new PriceTierEvaluator;

        self::assertSame('preferred', $evaluator->evaluate(new Money(1000, 'SAR'), $tiers, CarbonImmutable::parse('2027-01-01T10:00:00Z'), 100)->priceTierId);
        self::assertNull($evaluator->evaluate(new Money(1000, 'SAR'), $tiers, CarbonImmutable::parse('2027-01-01T11:00:00Z'), 100)->priceTierId);
    }

    public function test_capacity_threshold_and_currency_mismatch_fall_back_to_base(): void
    {
        $tier = (object) ['id' => 'last-ten', 'price_minor' => 1200, 'currency' => 'SAR', 'starts_at' => null, 'ends_at' => null, 'remaining_at_most' => 10, 'priority' => 1, 'status' => 'active'];
        $evaluator = new PriceTierEvaluator;

        self::assertSame(1200, $evaluator->evaluate(new Money(1000, 'SAR'), [$tier], CarbonImmutable::now(), 10)->money->minor);
        self::assertSame(1000, $evaluator->evaluate(new Money(1000, 'SAR'), [$tier], CarbonImmutable::now(), 11)->money->minor);
        self::assertSame(1000, $evaluator->evaluate(new Money(1000, 'USD'), [$tier], CarbonImmutable::now(), 1)->money->minor);
    }
}
