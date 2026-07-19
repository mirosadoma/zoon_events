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
        private ActivateBadgeTemplateAction $activator,
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
        ?string $orientation = null,
        ?string $backgroundColor = null,
        ?int $canvasWidth = null,
        ?int $canvasHeight = null,
    ): BadgeTemplate {
        $this->layoutValidator->validate($layout);

        $attributes = [
            'name' => $name,
            'layout' => $layout,
            'paper_size' => $paperSize,
            'printer_type' => $printerType,
            'orientation' => $orientation ?? 'portrait',
            'background_color' => $backgroundColor,
            'canvas_width' => $canvasWidth,
            'canvas_height' => $canvasHeight,
        ];

        if ($existing === null) {
            $template = BadgeTemplate::create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'status' => 'draft',
                ...$attributes,
            ]);

            event(new BadgeTemplateCreated($tenantId, $eventId, $template->id));

            $hasActive = BadgeTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('status', 'active')
                ->where('id', '!=', $template->id)
                ->exists();

            if (! $hasActive) {
                $this->activator->execute($template);
            }

            return $template->fresh() ?? $template;
        }

        $existing->forceFill($attributes)->save();

        event(new BadgeTemplateUpdated($tenantId, $eventId, $existing->id));

        if ($existing->status !== 'active') {
            $hasActive = BadgeTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('status', 'active')
                ->exists();

            if (! $hasActive) {
                $this->activator->execute($existing);
            }
        }

        return $existing->fresh() ?? $existing;
    }
}
