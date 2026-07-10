<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateDeleted;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Shared\Http\Problems\Phase3Problem;

final readonly class DeleteBadgeTemplateAction
{
    public function execute(string $tenantId, string $templateId): void
    {
        $template = BadgeTemplate::where('id', $templateId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($template === null) {
            throw Phase3Problem::make('badge_template_not_found');
        }

        $tenantId = $template->tenant_id;
        $template->delete();

        event(new BadgeTemplateDeleted($tenantId, $templateId));
    }
}
