<?php

namespace App\Modules\Audit\Contracts;

use App\Models\User;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;

interface AuditWriter
{
    public function write(
        string $scope,
        ?string $tenantId,
        string $action,
        string $outcome,
        ?User $actor = null,
        ?string $reasonCode = null,
        ?string $targetType = null,
        ?string $targetId = null,
        array $metadata = [],
        ?array $changeSummary = null,
    ): AuditLog;
}
