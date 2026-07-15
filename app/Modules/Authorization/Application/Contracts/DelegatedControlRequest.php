<?php

namespace App\Modules\Authorization\Application\Contracts;

use DateTimeImmutable;

final readonly class DelegatedControlRequest
{
    public function __construct(
        public int $organizerTenantId,
        public int $actorUserId,
        public int $eventId,
        public string $resourceModule,
        public string $resourceType,
        public string $resourcePublicReference,
        public string $requestedCapability,
        public DateTimeImmutable $now,
        public bool $existingPermissionAllowed,
        public ?string $delegationPublicId = null,
    ) {}
}
