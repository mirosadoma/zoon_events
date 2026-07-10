<?php

namespace App\Modules\Ticketing\Domain;

final readonly class FreeTicketAllocation
{
    /** @param array{en:string,ar:string} $ticketName */
    public function __construct(
        public string $holdId,
        public string $ticketTypeId,
        public array $ticketName,
        public string $currency,
    ) {}
}
