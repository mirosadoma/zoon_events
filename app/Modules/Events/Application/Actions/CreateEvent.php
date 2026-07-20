<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\AdminConsole\Application\Actions\SyncEventVenues;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Application\Support\ResolvesEventOrganizer;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class CreateEvent
{
    public function __construct(
        private AuditWriter $audit,
        private SyncEventVenues $venues,
        private ResolvesEventOrganizer $organizers,
    ) {}

    /** @param array<string,mixed> $attributes */
    public function execute(TenantContext $context, array $attributes): Event
    {
        return DB::transaction(function () use ($context, $attributes): Event {
            $branding = [
                'brand_reference' => $attributes['brand_reference'] ?? null,
                'domain_reference' => $attributes['domain_reference'] ?? null,
                'theme_config' => is_array($attributes['theme_config'] ?? null) ? $attributes['theme_config'] : null,
            ];
            $venues = $attributes['venues'] ?? [];
            $organizerUserId = isset($attributes['organizer_user_id']) ? (int) $attributes['organizer_user_id'] : null;
            unset(
                $attributes['brand_reference'],
                $attributes['domain_reference'],
                $attributes['theme_config'],
                $attributes['venues'],
                $attributes['organizer_user_id'],
            );
            $event = Event::query()->create([
                ...$attributes,
                'tenant_id' => $context->tenant->id,
                'status' => 'draft',
                'created_by_user_id' => $this->organizers->resolve($context, $organizerUserId),
            ]);

            if ($this->shouldPersistBranding($branding)) {
                EventBranding::query()->create([
                    'tenant_id' => $context->tenant->id,
                    'event_id' => $event->id,
                    'brand_reference' => $branding['brand_reference'] ?: ($event->slug.'-brand'),
                    'domain_reference' => $branding['domain_reference'] ?: config('app.url'),
                    'theme_config' => $branding['theme_config'] ?? [],
                    'content_en' => [],
                    'content_ar' => [],
                    'sender_name_en' => $event->name_en,
                    'sender_name_ar' => $event->name_ar,
                    'status' => 'active',
                ]);
            }

            $this->venues->execute($context->tenant->id, $event, $venues);
            $this->audit->writeTenant('event.created', 'succeeded', $context, targetType: 'event', targetId: $event->id);

            return $event->refresh();
        });
    }

    /** @param array{brand_reference:?string,domain_reference:?string,theme_config:?array} $branding */
    private function shouldPersistBranding(array $branding): bool
    {
        if (($branding['brand_reference'] ?? null) !== null && ($branding['domain_reference'] ?? null) !== null) {
            return true;
        }

        if (($branding['domain_reference'] ?? null) !== null) {
            return true;
        }

        $theme = $branding['theme_config'] ?? null;

        return is_array($theme) && $theme !== [];
    }
}
