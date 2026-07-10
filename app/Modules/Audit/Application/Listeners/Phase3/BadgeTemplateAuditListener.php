<?php

namespace App\Modules\Audit\Application\Listeners\Phase3;

use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateActivated;
use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateCreated;
use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateDeactivated;
use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateDeleted;
use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateUpdated;

final readonly class BadgeTemplateAuditListener
{
    public function __construct(private AuditWriter $audit) {}

    public function handleCreated(BadgeTemplateCreated $event): void
    {
        $this->write('badge_template.created', $event->tenantId, $event->templateId, $event->eventId);
    }

    public function handleUpdated(BadgeTemplateUpdated $event): void
    {
        $this->write('badge_template.updated', $event->tenantId, $event->templateId, $event->eventId);
    }

    public function handleActivated(BadgeTemplateActivated $event): void
    {
        $this->write('badge_template.activated', $event->tenantId, $event->templateId, $event->eventId);
    }

    public function handleDeactivated(BadgeTemplateDeactivated $event): void
    {
        $this->write('badge_template.deactivated', $event->tenantId, $event->templateId, $event->eventId);
    }

    public function handleDeleted(BadgeTemplateDeleted $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'badge_template.deleted',
            'succeeded',
            targetType: 'badge_template',
            targetId: $event->templateId,
        );
    }

    private function write(string $action, string $tenantId, string $templateId, string $eventId): void
    {
        $this->audit->write(
            'tenant',
            $tenantId,
            $action,
            'succeeded',
            targetType: 'badge_template',
            targetId: $templateId,
            metadata: ['event_id' => $eventId],
        );
    }
}
