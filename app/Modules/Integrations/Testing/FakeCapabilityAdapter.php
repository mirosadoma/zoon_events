<?php

namespace App\Modules\Integrations\Testing;

use App\Modules\Integrations\Application\AdapterInvocationContext;
use App\Modules\Integrations\Contracts\AdapterDescriptor;
use App\Modules\Integrations\Contracts\CapabilityAdapter;
use App\Modules\Integrations\Domain\AdapterResult;
use App\Modules\Integrations\Domain\AdapterRetryPolicy;
use App\Modules\Integrations\Domain\AdapterStatus;

class FakeCapabilityAdapter implements CapabilityAdapter
{
    public function __construct(private string $scenario = 'success') {}

    public function scenario(string $scenario): self
    {
        $clone = clone $this;
        $clone->scenario = $scenario;

        return $clone;
    }

    public function descriptor(): AdapterDescriptor
    {
        return new AdapterDescriptor(
            key: 'fake-capability',
            capability: 'foundation.fake',
            version: '1.0.0',
            testingOnly: true,
        );
    }

    public function execute(AdapterInvocationContext $context, array $request): AdapterResult
    {
        if ($this->scenario === 'timeout_before_send') {
            return new AdapterResult(AdapterStatus::Unavailable, AdapterRetryPolicy::Safe, 'timeout_before_send');
        }
        if ($this->scenario === 'timeout_unknown') {
            return new AdapterResult(AdapterStatus::Unknown, AdapterRetryPolicy::ReconcileFirst, 'timeout_unknown_outcome');
        }
        if ($this->scenario === 'rejected') {
            return new AdapterResult(AdapterStatus::Rejected, AdapterRetryPolicy::Never, 'provider_rejected');
        }
        if ($this->scenario === 'offline') {
            return new AdapterResult(AdapterStatus::Unavailable, AdapterRetryPolicy::Safe, 'provider_unavailable');
        }

        return new AdapterResult(
            status: AdapterStatus::Succeeded,
            retryPolicy: AdapterRetryPolicy::Never,
            reasonCode: null,
            data: [
                'scope' => $context->scope,
                'tenant_id' => $context->tenantId,
                'accepted' => true,
                'request_digest' => hash('sha256', json_encode($request, JSON_THROW_ON_ERROR)),
            ],
            metadata: [
                'adapter' => $this->descriptor()->key,
            ],
        );
    }
}
