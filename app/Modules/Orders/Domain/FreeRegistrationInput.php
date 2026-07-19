<?php

namespace App\Modules\Orders\Domain;

use Carbon\CarbonImmutable;

final readonly class FreeRegistrationInput
{
    /**
     * @param  array<string,mixed>  $answers
     * @param  array<string,mixed>  $consent
     * @param  array{first_name:string,last_name:string,email:string,phone?:string}  $buyer
     * @param  array{first_name:string,last_name:string,email:string,phone?:string}  $attendee
     */
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $formVersionId,
        public string $ticketTypeId,
        public string $idempotencyKey,
        public array $answers,
        public array $consent,
        public array $buyer,
        public array $attendee,
        public string $locale,
        public CarbonImmutable $credentialExpiresAt,
        public bool $bypassIdentityGateForCredential = false,
        public ?string $eventCategoryId = null,
        public ?int $priceMinorOverride = null,
        public ?string $currencyOverride = null,
        public ?string $eventVenueId = null,
    ) {}
}
