<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRoleAssignment;
use App\Modules\Notifications\Infrastructure\Persistence\Models\InAppNotification;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use App\Modules\Tenancy\Infrastructure\Persistence\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;

final class InAppNotificationDispatcher
{
    private const ROUTING_RULES = [
        'event.*'           => ['event.view'],
        'role.*'            => ['role.view'],
        'membership.*'      => ['membership.view'],
        'credential.*'      => ['credential.view'],
        'scan.*'            => ['checkin.dashboard.view'],
        'badge_print.*'     => ['badge.print'],
        'badge_template.*'  => ['badge.template.manage'],
        'kiosk.*'           => ['kiosk.manage'],
        'access.*'          => ['acs.events.view'],
        'acs_emergency.*'   => ['acs.emergency.manage'],
        'acs_zone.*'        => ['acs.configure'],
        'acs_lane.*'        => ['acs.configure'],
        'acs_rule.*'        => ['acs.configure'],
        'acs_integration.*' => ['acs.configure'],
        'identity_*'        => ['identity.review'],
        'venue*'            => ['venue.manage'],
        'rental.*'          => ['rentals.approve', 'marketplace.manage'],
        'delegation.*'      => ['venue.manage'],
        'statement.*'       => ['reports.view'],
        'dispute.*'         => ['reports.view', 'platform.marketplace.disputes.manage'],
        'audit.*'           => ['audit.view'],
        'configuration.*'   => ['configuration.view'],
        'registration.*'    => ['registration.manage'],
        'ticket_type.*'     => ['ticketing.manage'],
        'inventory.*'       => ['order.view'],
        'payment.*'         => ['order.view'],
        'refund.*'          => ['payment.refund'],
        'order.*'           => ['order.view'],
        'attendee.*'        => ['attendee.view'],
        'notification.*'    => ['membership.view'],
        'offline_scan_batch.*' => ['checkin.dashboard.view'],
        'wallet_pass.*'     => ['wallet.pass.view'],
    ];

    /** @param  array<string, mixed>  $metadata */
    public function dispatch(
        string $action,
        ?string $tenantId,
        ?int $actorId,
        ?string $actorName,
        ?string $targetType,
        ?string $targetId,
        array $metadata = [],
    ): void {
        $permissions = $this->resolvePermissions($action);
        if ($permissions === []) {
            return;
        }

        $type = $this->resolveType($action);
        $link = $this->resolveLink($action, $targetType, $targetId, $tenantId, $metadata);

        $recipientUserIds = $tenantId !== null
            ? $this->resolveTenantRecipients($tenantId, $permissions, $actorId)
            : $this->resolvePlatformRecipients($permissions, $actorId);

        if ($recipientUserIds === []) {
            return;
        }

        $now = now();
        $data = $this->notificationData($metadata);
        $rows = array_map(fn (int $userId) => [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'type' => $type,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'actor_name' => $actorName,
            'link' => $link,
            'data' => $data === null ? null : json_encode($data),
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $recipientUserIds);

        InAppNotification::insert($rows);
    }

    /** @return list<string> */
    private function resolvePermissions(string $action): array
    {
        foreach (self::ROUTING_RULES as $pattern => $permissions) {
            if ($this->matchesPattern($action, $pattern)) {
                return $permissions;
            }
        }

        return [];
    }

    private function matchesPattern(string $action, string $pattern): bool
    {
        $regex = '/^' . str_replace(['*', '.'], ['[a-z0-9_]*', '\\.'], $pattern) . '$/';

        return (bool) preg_match($regex, $action);
    }

    private function resolveType(string $action): string
    {
        $dot = strpos($action, '.');
        $underscore = strpos($action, '_');

        if ($dot !== false) {
            return substr($action, 0, $dot);
        }
        if ($underscore !== false) {
            return substr($action, 0, $underscore);
        }

        return $action;
    }

    /** @return list<int> */
    private function resolveTenantRecipients(string $tenantId, array $permissions, ?int $excludeUserId): array
    {
        $userIds = TenantMembership::query()
            ->where('tenant_memberships.tenant_id', $tenantId)
            ->where('tenant_memberships.status', 'active')
            ->whereHas('assignments', function ($q) use ($tenantId, $permissions) {
                $q->withoutGlobalScope(TenantScope::class)
                    ->where('tenant_role_assignments.tenant_id', $tenantId)
                    ->whereNull('tenant_role_assignments.revoked_at')
                    ->where(function ($sq) {
                        $sq->whereNull('tenant_role_assignments.expires_at')
                            ->orWhere('tenant_role_assignments.expires_at', '>', now());
                    })
                    ->whereHas('role', function ($rq) use ($permissions) {
                        $rq->withoutGlobalScope(TenantScope::class)
                            ->whereHas('permissions', fn ($pq) => $pq
                                ->whereIn('key', $permissions)
                                ->where('scope', 'tenant'));
                    });
            })
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($excludeUserId !== null) {
            $userIds = array_values(array_filter($userIds, fn (int $id) => $id !== $excludeUserId));
        }

        return $userIds;
    }

    /** @return list<int> */
    private function resolvePlatformRecipients(array $permissions, ?int $excludeUserId): array
    {
        $userIds = DB::table('platform_role_assignments')
            ->join('platform_roles', 'platform_roles.id', '=', 'platform_role_assignments.platform_role_id')
            ->join('platform_role_permissions', 'platform_role_permissions.platform_role_id', '=', 'platform_roles.id')
            ->join('permissions', 'permissions.id', '=', 'platform_role_permissions.permission_id')
            ->whereNull('platform_role_assignments.revoked_at')
            ->where(function ($q) {
                $q->whereNull('platform_role_assignments.expires_at')
                    ->orWhere('platform_role_assignments.expires_at', '>', now());
            })
            ->whereIn('permissions.key', $permissions)
            ->where('permissions.scope', 'platform')
            ->distinct()
            ->pluck('platform_role_assignments.user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($excludeUserId !== null) {
            $userIds = array_values(array_filter($userIds, fn (int $id) => $id !== $excludeUserId));
        }

        return $userIds;
    }

    /** @param  array<string, mixed>  $metadata */
    private function resolveLink(
        string $action,
        ?string $targetType,
        ?string $targetId,
        ?string $tenantId,
        array $metadata = [],
    ): ?string {
        $eventId = isset($metadata['event_id']) && (is_string($metadata['event_id']) || is_numeric($metadata['event_id']))
            ? (string) $metadata['event_id']
            : null;

        return match (true) {
            str_starts_with($action, 'event.') => $targetId ? "/tenant/events/{$targetId}" : '/tenant/events',
            str_starts_with($action, 'registration.') => $eventId
                ? "/tenant/events/{$eventId}/attendees"
                : ($targetId ? "/tenant/events/{$targetId}" : null),
            str_starts_with($action, 'ticket_type.') => $targetId ? "/tenant/events/{$targetId}/ticket-types" : null,
            str_starts_with($action, 'order.') => $eventId
                ? "/tenant/events/{$eventId}/orders"
                : ($targetId ? "/tenant/events/{$targetId}/orders" : null),
            str_starts_with($action, 'payment.') => $eventId
                ? "/tenant/events/{$eventId}/orders"
                : ($targetId ? "/tenant/events/{$targetId}/orders" : null),
            str_starts_with($action, 'attendee.') => $eventId
                ? "/tenant/events/{$eventId}/attendees"
                : ($targetId ? "/tenant/events/{$targetId}/attendees" : null),
            str_starts_with($action, 'credential.') => $eventId
                ? "/tenant/events/{$eventId}/credentials"
                : ($targetId ? "/tenant/events/{$targetId}/credentials" : null),
            str_starts_with($action, 'scan.') => $eventId
                ? "/tenant/events/{$eventId}/check-in-dashboard"
                : '/tenant/events',
            str_starts_with($action, 'offline_scan') => $eventId
                ? "/tenant/events/{$eventId}/scan-events"
                : '/tenant/events',
            str_starts_with($action, 'wallet_pass.') => $eventId
                ? "/tenant/events/{$eventId}/wallet-passes"
                : '/tenant/events',
            str_starts_with($action, 'badge_print.') => $eventId
                ? "/tenant/events/{$eventId}/badge-print-jobs"
                : '/tenant/events',
            str_starts_with($action, 'badge_template.') => $eventId
                ? "/tenant/events/{$eventId}/badge-templates"
                : '/tenant/events',
            str_starts_with($action, 'kiosk.') => $eventId
                ? "/tenant/events/{$eventId}/kiosks"
                : '/tenant/events',
            str_starts_with($action, 'access.') => $eventId ? "/tenant/events/{$eventId}/acs" : '/tenant/events',
            str_starts_with($action, 'acs_') => $eventId ? "/tenant/events/{$eventId}/acs" : '/tenant/events',
            str_starts_with($action, 'identity_') => $eventId ? "/tenant/events/{$eventId}/identity" : '/tenant/events',
            str_starts_with($action, 'role.') => '/admin/roles',
            str_starts_with($action, 'membership.') => '/admin/users',
            str_starts_with($action, 'venue') => '/tenant/venues',
            str_starts_with($action, 'rental.') => '/tenant/marketplace/rentals',
            str_starts_with($action, 'delegation.') => '/tenant/marketplace/rentals',
            str_starts_with($action, 'statement.') => '/tenant/marketplace/statements',
            str_starts_with($action, 'dispute.') => '/tenant/marketplace/statements',
            str_starts_with($action, 'audit.') => '/admin/audit-logs',
            str_starts_with($action, 'configuration.') => '/admin/tenant-settings',
            default => null,
        };
    }

    /** @param  array<string, mixed>  $metadata
     *  @return array<string, mixed>|null
     */
    private function notificationData(array $metadata): ?array
    {
        $keys = ['event_id', 'attendee_id', 'credential_id'];
        $data = [];

        foreach ($keys as $key) {
            if (isset($metadata[$key]) && (is_string($metadata[$key]) || is_numeric($metadata[$key]))) {
                $data[$key] = (string) $metadata[$key];
            }
        }

        return $data === [] ? null : $data;
    }
}
