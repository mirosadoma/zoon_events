<?php

namespace App\Modules\Ticketing\Domain;

final readonly class PaidTicketAllocation
{
    /** @param array{en:string,ar:string} $ticketName */
    public function __construct(
        public string $holdId,
        public string $ticketTypeId,
        public array $ticketName,
        public int $priceMinor,
        public string $currency,
        public ?string $priceTierId,
    ) {}
}
