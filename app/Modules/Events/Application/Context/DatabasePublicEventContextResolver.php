<?php

namespace App\Modules\Events\Application\Context;

use App\Modules\Events\Contracts\PublicEventContextResolver;
use App\Modules\Events\Domain\Context\PublicEventContext;
use Illuminate\Support\Facades\DB;

final class DatabasePublicEventContextResolver implements PublicEventContextResolver
{
    public function resolve(string $host, string $eventSlug): ?PublicEventContext
    {
        $records = DB::table('events')
            ->join('event_branding', function ($join): void {
                $join->on('event_branding.tenant_id', '=', 'events.tenant_id')
                    ->on('event_branding.event_id', '=', 'events.id');
            })
            ->join('tenants', 'tenants.id', '=', 'events.tenant_id')
            ->where('event_branding.domain_reference', mb_strtolower($host))
            ->where('event_branding.status', 'active')
            ->where('events.slug', $eventSlug)
            ->whereIn('events.status', ['published', 'registration_open', 'registration_closed', 'live'])
            ->where('tenants.status', 'active')
            ->select(['events.tenant_id', 'events.id', 'events.end_at'])
            ->limit(2)
            ->get();
        if ($records->count() !== 1) {
            return null;
        }
        $record = $records->first();

        return new PublicEventContext($record->tenant_id, $record->id, mb_strtolower($host), $eventSlug, (string) $record->end_at);
    }
}
