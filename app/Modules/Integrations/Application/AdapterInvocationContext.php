<?php

namespace App\Modules\Integrations\Application;

final readonly class AdapterInvocationContext
{
    public function __construct(
        public string $scope,
        public ?string $tenantId,
        public string $actorId,
        public string $correlationId,
        public ?string $idempotencyKey,
        public string $locale,
        public int $timeoutMs,
        public string $dataClassification,
    ) {}
}
