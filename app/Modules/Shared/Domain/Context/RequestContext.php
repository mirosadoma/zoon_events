<?php

namespace App\Modules\Shared\Domain\Context;

use App\Modules\Shared\Domain\Locale;

final readonly class RequestContext
{
    public function __construct(
        public CorrelationId $correlationId,
        public RequestId $requestId,
        public Locale $locale,
    ) {}
}
