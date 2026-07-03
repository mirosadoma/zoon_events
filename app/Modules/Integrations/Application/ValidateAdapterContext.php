<?php

namespace App\Modules\Integrations\Application;

use App\Exceptions\FoundationException;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;

final class ValidateAdapterContext
{
    public function __construct(
        private readonly TenantContextStore $tenants,
        private readonly RequestContextStore $requests,
    ) {}

    public function tenant(string $dataClassification, int $timeoutMs, ?string $idempotencyKey = null): AdapterInvocationContext
    {
        $context = $this->tenants->current();
        $request = $this->requests->current();
        if ($timeoutMs < 100 || $timeoutMs > 30000) {
            throw FoundationException::validation('adapter_timeout_invalid', 'The adapter timeout budget is invalid.');
        }

        return new AdapterInvocationContext('tenant', $context->tenant->id, $context->actor->id, $request?->correlationId->value ?? '', $idempotencyKey, $request?->locale->value ?? 'en', $timeoutMs, $dataClassification);
    }

    public function validate(AdapterInvocationContext $context): void
    {
        if ($context->scope === 'tenant') {
            $trusted = $this->tenants->current();
            if ($context->tenantId !== $trusted->tenant->id || $context->actorId !== $trusted->actor->id) {
                throw FoundationException::forbidden('adapter_context_invalid', 'The adapter tenant context is not trusted.');
            }

            return;
        }

        if ($context->scope !== 'platform' || $context->tenantId !== null || $context->actorId === '') {
            throw FoundationException::forbidden('adapter_context_invalid', 'An explicit trusted adapter scope is required.');
        }
    }
}
