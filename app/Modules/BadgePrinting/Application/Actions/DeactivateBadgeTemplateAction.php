<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateDeactivated;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;

final readonly class DeactivateBadgeTemplateAction
{
    public function execute(BadgeTemplate $target): void
    {
        if ($target->status !== 'active') {
            return;
        }

        $target->forceFill(['status' => 'inactive'])->save();

        event(new BadgeTemplateDeactivated($target->tenant_id, $target->event_id, $target->id));
    }
}
