<?php

namespace App\Modules\Ticketing\Contracts;

interface PublicTicketCatalog
{
    /** @return list<array{id:string,code:string,name:array{en:string,ar:string},price_minor:int,currency:string,status:string}> */
    public function forEvent(string $tenantId, string $eventId): array;
}
