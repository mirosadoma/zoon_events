<?php

namespace App\Modules\BadgePrinting\Domain\Events;

final readonly class BadgeTemplateActivated
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $templateId,
    ) {}
}
