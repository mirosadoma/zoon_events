<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Modules\BadgePrinting\Domain\Events\BadgeTemplateActivated;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use Illuminate\Support\Facades\DB;

final readonly class ActivateBadgeTemplateAction
{
    public function execute(BadgeTemplate $target): void
    {
        DB::transaction(function () use ($target): void {
            $rows = BadgeTemplate::query()
                ->where('tenant_id', $target->tenant_id)
                ->where('event_id', $target->event_id)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                if ($row->id === $target->id) {
                    $row->forceFill(['status' => 'active'])->save();
                    $target->forceFill(['status' => 'active']);

                    continue;
                }

                if ($row->status === 'active') {
                    $row->forceFill(['status' => 'inactive'])->save();
                }
            }
        });

        event(new BadgeTemplateActivated($target->tenant_id, $target->event_id, $target->id));
    }
}
