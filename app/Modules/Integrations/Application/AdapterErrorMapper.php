<?php

namespace App\Modules\Integrations\Application;

use App\Exceptions\FoundationException;
use App\Modules\Integrations\Domain\AdapterResult;
use App\Modules\Integrations\Domain\AdapterStatus;

final class AdapterErrorMapper
{
    public function toException(AdapterResult $result): FoundationException
    {
        return match ($result->status) {
            AdapterStatus::Rejected => FoundationException::validation($result->reasonCode ?? 'provider_rejected', 'The provider rejected the request.'),
            AdapterStatus::Unavailable, AdapterStatus::Unknown => new FoundationException($result->reasonCode ?? 'dependency_unavailable', 503, 'Dependency unavailable', 'An external dependency is temporarily unavailable.'),
            default => new FoundationException('internal_adapter_failure', 503, 'Dependency unavailable', 'The adapter could not complete the operation.'),
        };
    }
}
