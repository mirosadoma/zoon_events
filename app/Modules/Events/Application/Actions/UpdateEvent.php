<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\AdminConsole\Application\Actions\SyncEventVenues;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Application\Support\ResolvesEventOrganizer;
use App\Modules\Events\Domain\Events\EventUpdated;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class UpdateEvent
{
    public function __construct(
        private AuditWriter $audit,
        private SyncEventVenues $venues,
        private ResolvesEventOrganizer $organizers,
    ) {}

    /** @param array<string,mixed> $attributes */
    public function execute(TenantContext $context, Event $event, array $attributes): Event
    {
        return DB::transaction(function () use ($context, $event, $attributes): Event {
            $brandingKeys = ['brand_reference', 'domain_reference', 'theme_config'];
            $branding = array_intersect_key($attributes, array_flip($brandingKeys));
            $venueRows = $attributes['venues'] ?? null;
            unset(
                $attributes['brand_reference'],
                $attributes['domain_reference'],
                $attributes['theme_config'],
                $attributes['venues'],
            );

            if (array_key_exists('organizer_user_id', $attributes)) {
                if ($this->organizers->requiresSelection($context)) {
                    $attributes['created_by_user_id'] = $this->organizers->resolve(
                        $context,
                        $attributes['organizer_user_id'] !== null ? (int) $attributes['organizer_user_id'] : null,
                    );
                }
                unset($attributes['organizer_user_id']);
            }

            $before = $event->only(array_keys($attributes));
            $event->fill($attributes);
            if ($event->status === 'draft') {
                $event->status = 'configured';
            }
            $event->save();

            if ($branding !== []) {
                $existing = EventBranding::query()
                    ->where('tenant_id', $context->tenant->id)
                    ->where('event_id', $event->id)
                    ->first();

                $themeConfig = $branding['theme_config'] ?? null;
                if (is_array($themeConfig)) {
                    $themeConfig = array_merge(
                        is_array($existing?->theme_config) ? $existing->theme_config : [],
                        $themeConfig,
                    );
                } else {
                    $themeConfig = $existing?->theme_config;
                }

                EventBranding::query()->updateOrCreate(
                    ['tenant_id' => $context->tenant->id, 'event_id' => $event->id],
                    [
                        'brand_reference' => $branding['brand_reference']
                            ?? $existing?->brand_reference
                            ?? ($event->slug.'-brand'),
                        'domain_reference' => $branding['domain_reference']
                            ?? $existing?->domain_reference
                            ?? config('app.url'),
                        'theme_config' => $themeConfig ?? [],
                        'content_en' => $existing?->content_en ?? [],
                        'content_ar' => $existing?->content_ar ?? [],
                        'sender_name_en' => $event->name_en,
                        'sender_name_ar' => $event->name_ar,
                        'status' => 'active',
                    ],
                );
            }

            if (is_array($venueRows)) {
                $this->venues->execute($context->tenant->id, $event, $venueRows);
            }
            $this->audit->writeTenant('event.updated', 'succeeded', $context, targetType: 'event', targetId: $event->id, changeSummary: ['before' => $before, 'after' => $event->only(array_keys($attributes))]);

            $syncFields = ['start_at', 'end_at', 'timezone', 'location_name_en', 'location_name_ar', 'location_address_en', 'location_address_ar'];
            $syncRelevant = array_intersect_key($attributes, array_flip($syncFields)) !== [] || $branding !== [];
            if ($syncRelevant) {
                event(new EventUpdated($context->tenant->id, $event->id));
            }

            return $event->refresh();
        });
    }
}
