<?php

namespace App\Modules\BadgePrinting\Domain\Events;

final readonly class BadgeTemplateDeleted
{
    public function __construct(
        public string $tenantId,
        public string $templateId,
    ) {}
}
