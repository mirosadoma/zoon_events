<?php

namespace App\Modules\Ticketing\Application\Pricing;

use App\Modules\Ticketing\Domain\ValueObjects\Money;
use App\Modules\Ticketing\Domain\ValueObjects\PriceQuote;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class PriceTierEvaluator
{
    /**
     * @param  iterable<object{id:string,price_minor:int,currency:string,starts_at:mixed,ends_at:mixed,remaining_at_most:?int,priority:int,status:string}>  $tiers
     */
    public function evaluate(Money $base, iterable $tiers, CarbonImmutable $at, int $remaining): PriceQuote
    {
        $matching = collect($tiers)
            ->filter(function (object $tier) use ($base, $at, $remaining): bool {
                if ($tier->status !== 'active' || $tier->currency !== $base->currency) {
                    return false;
                }
                $starts = $tier->starts_at === null ? null : CarbonImmutable::parse($tier->starts_at);
                $ends = $tier->ends_at === null ? null : CarbonImmutable::parse($tier->ends_at);

                return ($starts === null || $at->greaterThanOrEqualTo($starts))
                    && ($ends === null || $at->isBefore($ends))
                    && ($tier->remaining_at_most === null || $remaining <= $tier->remaining_at_most);
            })
            ->sortBy('priority')
            ->values();

        if ($matching->count() > 1 && $matching[0]->priority === $matching[1]->priority) {
            throw new InvalidArgumentException('Active price tiers are ambiguous.');
        }
        $tier = $matching->first();

        return $tier === null
            ? new PriceQuote($base, null, $at)
            : new PriceQuote(new Money($tier->price_minor, $tier->currency), $tier->id, $at);
    }
}
