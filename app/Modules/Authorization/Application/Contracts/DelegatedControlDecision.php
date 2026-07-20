<?php

namespace App\Modules\Authorization\Application\Contracts;

use DateTimeImmutable;

final readonly class DelegatedControlDecision
{
    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public ?string $rentalPublicId = null,
        public ?string $delegationPublicId = null,
        public ?DateTimeImmutable $startsAt = null,
        public ?DateTimeImmutable $endsAt = null,
    ) {}
}
