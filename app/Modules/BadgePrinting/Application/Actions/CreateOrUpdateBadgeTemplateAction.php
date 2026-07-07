<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Modules\BadgePrinting\Application\Support\BadgeLayoutValidator;
use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateCreated;
use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateUpdated;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;

final readonly class CreateOrUpdateBadgeTemplateAction
{
    public function __construct(
        private BadgeLayoutValidator $layoutValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $layout
     */
    public function execute(
        string $tenantId,
        string $eventId,
        ?BadgeTemplate $existing,
        string $name,
        array $layout,
        string $paperSize,
        string $printerType,
    ): BadgeTemplate {
        $this->layoutValidator->validate($layout);

        if ($existing === null) {
            $template = BadgeTemplate::create([
                'tenant_id'    => $tenantId,
                'event_id'     => $eventId,
                'name'         => $name,
                'layout'       => $layout,
                'paper_size'   => $paperSize,
                'printer_type' => $printerType,
                'status'       => 'draft',
            ]);

            event(new BadgeTemplateCreated($tenantId, $eventId, $template->id));

            return $template;
        }

        $existing->forceFill([
            'name'         => $name,
            'layout'       => $layout,
            'paper_size'   => $paperSize,
            'printer_type' => $printerType,
        ])->save();

        event(new BadgeTemplateUpdated($tenantId, $eventId, $existing->id));

        return $existing;
    }
}
