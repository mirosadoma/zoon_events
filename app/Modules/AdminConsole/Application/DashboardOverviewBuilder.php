<?php

namespace App\Modules\AdminConsole\Application;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Tenancy\Domain\Context\TenantContext;

final class DashboardOverviewBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(?TenantContext $context): array
    {
        if ($context === null) {
            return [
                'events_total' => 0,
                'events_published' => 0,
                'attendees_total' => 0,
                'orders_total' => 0,
                'credentials_issued' => 0,
                'checkins_today' => 0,
                'kiosks_active' => 0,
                'gates_active' => 0,
                'scans_failed' => 0,
                'recent_audit_events' => [],
            ];
        }

        $tenantId = $context->tenant->id;
        $todayStart = now()->startOfDay();

        $eventsQuery = Event::query()->where('tenant_id', $tenantId);
        $eventIds = (clone $eventsQuery)->pluck('id');

        $recentAudit = AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->latest('occurred_at')
            ->limit(5)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'actor' => $log->actor_id ? "User {$log->actor_id}" : 'System',
                'action' => $log->action,
                'outcome' => $log->outcome,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
            ])
            ->all();

        return [
            'events_total' => (clone $eventsQuery)->count(),
            'events_published' => (clone $eventsQuery)->where('status', 'published')->count(),
            'attendees_total' => Attendee::query()->whereIn('event_id', $eventIds)->count(),
            'orders_total' => Order::query()->whereIn('event_id', $eventIds)->count(),
            'credentials_issued' => Credential::query()->whereIn('event_id', $eventIds)->count(),
            'checkins_today' => ScanEvent::query()
                ->whereIn('event_id', $eventIds)
                ->where('result', 'accepted')
                ->where('scanned_at', '>=', $todayStart)
                ->count(),
            'kiosks_active' => Kiosk::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count(),
            'gates_active' => AcsLane::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count(),
            'scans_failed' => ScanEvent::query()
                ->whereIn('event_id', $eventIds)
                ->where('result', 'rejected')
                ->where('scanned_at', '>=', $todayStart)
                ->count(),
            'recent_audit_events' => $recentAudit,
        ];
    }
}
