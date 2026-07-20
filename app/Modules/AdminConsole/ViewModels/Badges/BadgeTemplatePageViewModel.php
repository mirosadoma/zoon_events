<?php

namespace App\Modules\AdminConsole\ViewModels\Badges;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Support\Collection;

final readonly class BadgeTemplatePageViewModel
{
    /**
     * @param  Collection<int, BadgeTemplate>  $templates
     * @return array{event: array<string, mixed>, tenantId: string, templates: list<array<string, mixed>>}
     */
    public function index(Event $event, string $tenantId, Collection $templates): array
    {
        return [
            'event' => $this->eventRow($event),
            'tenantId' => $tenantId,
            'templates' => $templates->map(fn (BadgeTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'layout' => $template->layout,
                'paper_size' => $template->paper_size,
                'printer_type' => $template->printer_type,
                'orientation' => $template->orientation ?? 'portrait',
                'background_color' => $template->background_color,
                'background_gradient' => $template->background_gradient,
                'canvas_width' => $template->canvas_width,
                'canvas_height' => $template->canvas_height,
                'status' => $template->status,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function eventRow(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
        ];
    }
}
