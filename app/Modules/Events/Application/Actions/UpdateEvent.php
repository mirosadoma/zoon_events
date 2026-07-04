<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class UpdateEvent
{
    public function __construct(private AuditWriter $audit) {}

    /** @param array<string,mixed> $attributes */
    public function execute(TenantContext $context, Event $event, array $attributes): Event
    {
        return DB::transaction(function () use ($context, $event, $attributes): Event {
            $branding = array_intersect_key($attributes, array_flip(['brand_reference', 'domain_reference']));
            unset($attributes['brand_reference'], $attributes['domain_reference']);
            $before = $event->only(array_keys($attributes));
            $event->fill($attributes);
            if ($event->status === 'draft') {
                $event->status = 'configured';
            }
            $event->save();
            if ($branding !== []) {
                EventBranding::query()->updateOrCreate(
                    ['tenant_id' => $context->tenant->id, 'event_id' => $event->id],
                    [
                        ...$branding,
                        'content_en' => [],
                        'content_ar' => [],
                        'sender_name_en' => $event->name_en,
                        'sender_name_ar' => $event->name_ar,
                        'status' => 'active',
                    ],
                );
            }
            $this->audit->writeTenant('event.updated', 'succeeded', $context, targetType: 'event', targetId: $event->id, changeSummary: ['before' => $before, 'after' => $event->only(array_keys($attributes))]);

            return $event->refresh();
        });
    }
}
