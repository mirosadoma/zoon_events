<?php

namespace App\Modules\Integrations\Contracts;

use App\Modules\Integrations\Application\AdapterInvocationContext;
use App\Modules\Integrations\Domain\AdapterResult;

interface CapabilityAdapter
{
    public function descriptor(): AdapterDescriptor;

    /**
     * @param  array<string, mixed>  $request
     */
    public function execute(AdapterInvocationContext $context, array $request): AdapterResult;
}
