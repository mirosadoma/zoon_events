<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Application\Queries\SearchAuditLogs;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\FeatureFlags\Infrastructure\Persistence\Models\FeatureFlag;
use App\Modules\Operations\Application\Health\HealthService;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Http\Controllers\ConfigurationController;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformPageController extends Controller
{
    public function __construct(
        private readonly HealthService $health,
        private readonly SearchAuditLogs $searchAuditLogs,
        private readonly ConfigurationController $configurationController,
    ) {}

    public function show(string $locale, string $section): Response
    {
        unset($locale);

        $permission = [
            'all-events' => 'platform.event.view',
            'users' => 'platform.user.view',
            'roles' => 'platform.role.view',
            'tenants' => 'platform.tenant.view',
            'audit' => 'platform.audit.view',
            'health' => 'operations.health.view',
            'feature-flags' => 'platform.feature_flag.view',
            'configuration' => 'platform.configuration.view',
        ][$section] ?? null;

        abort_if($permission === null, 404);
        Gate::authorize($permission);

        return Inertia::render('platform/Section', [
            'section' => $section,
            'canManage' => $this->canManage($section),
            'rows' => $this->rowsFor($section),
            'health' => $section === 'health' ? $this->health->readiness()->toArray() : null,
            'platformRoles' => in_array($section, ['users', 'roles']) ? $this->platformRoles() : [],
        ]);
    }

    public function configuration(string $locale): Response
    {
        return $this->show($locale, 'configuration');
    }

    private function canManage(string $section): bool
    {
        return match ($section) {
            'all-events' => Gate::allows('platform.event.view'),
            'users' => Gate::allows('platform.user.manage'),
            'roles' => Gate::allows('platform.role.manage'),
            'tenants' => Gate::allows('platform.tenant.manage'),
            'feature-flags' => Gate::allows('platform.feature_flag.manage'),
            default => false,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsFor(string $section): array
    {
        return match ($section) {
            'all-events' => $this->allEvents(),
            'users' => $this->platformUsers(),
            'roles' => $this->platformRoles(),
            'tenants' => Tenant::query()->latest()->limit(100)->get()->map(function (Tenant $tenant): array {
                $membership = TenantMembership::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'active')
                    ->first();
                $adminUser = $membership ? User::query()->find($membership->user_id) : null;

                return [
                    'id' => (string) $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status->value,
                    'default_locale' => $tenant->default_locale,
                    'timezone' => $tenant->timezone,
                    'created_at' => $tenant->created_at?->toIso8601String(),
                    'admin' => $adminUser ? [
                        'id' => (string) $adminUser->id,
                        'name' => $adminUser->name,
                        'email' => $adminUser->email,
                        'phone' => $adminUser->phone ?? null,
                    ] : null,
                ];
            })->all(),
            'audit' => collect($this->searchAuditLogs->platform([])->items)->map(fn (AuditLog $log): array => [
                'id' => (string) $log->id,
                'action' => $log->action,
                'outcome' => $log->outcome,
                'actor_id' => $log->actor_id,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
            ])->all(),
            'feature-flags' => FeatureFlag::query()->latest()->limit(100)->get()->map(fn (FeatureFlag $flag): array => [
                'id' => (string) $flag->id,
                'key' => $flag->key,
                'name' => $flag->name,
                'status' => $flag->status,
                'value_type' => $flag->value_type,
                'default_value' => $flag->default_value,
            ])->all(),
            'configuration' => $this->configurationSchemas(),
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    private function allEvents(): array
    {
        return Event::query()
            ->withoutGlobalScopes()
            ->latest('created_at')
            ->limit(200)
            ->get()
            ->map(function (Event $event): array {
                $tenant = Tenant::query()->find($event->tenant_id);

                return [
                    'id' => (string) $event->id,
                    'name' => $event->name_en ?: $event->name_ar,
                    'name_ar' => $event->name_ar,
                    'slug' => $event->slug,
                    'organizer' => $tenant?->name ?? '—',
                    'tenant_id' => (string) $event->tenant_id,
                    'tenant_slug' => $tenant?->slug,
                    'status' => $event->status,
                    'event_type' => $event->event_type,
                    'timezone' => $event->timezone,
                    'start_at' => EventWallClockDateTime::toIso8601($event->start_at, (string) $event->timezone),
                    'created_at' => $event->created_at?->toIso8601String(),
                ];
            })->all();
    }

    /** @return list<array<string, mixed>> */
    private function platformUsers(): array
    {
        return User::query()
            ->whereHas('platformAssignments')
            ->latest()
            ->limit(100)
            ->get()
            ->map(function (User $user): array {
                $roleIds = DB::table('platform_role_assignments')
                    ->where('user_id', $user->id)
                    ->whereNull('revoked_at')
                    ->pluck('platform_role_id');

                $roles = PlatformRole::query()->whereIn('id', $roleIds)->get();

                return [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status instanceof LifecycleStatus ? $user->status->value : $user->status,
                    'created_at' => $user->created_at?->toIso8601String(),
                    'roles' => $roles->map(fn (PlatformRole $role): array => [
                        'id' => (string) $role->id,
                        'name' => $role->name,
                    ])->all(),
                ];
            })->all();
    }

    /** @return list<array<string, mixed>> */
    private function platformRoles(): array
    {
        return PlatformRole::query()->latest()->limit(100)->get()->map(function (PlatformRole $role): array {
            $permissionKeys = DB::table('platform_role_permissions as prp')
                ->join('permissions as p', 'p.id', '=', 'prp.permission_id')
                ->where('prp.platform_role_id', $role->id)
                ->pluck('p.key')
                ->all();

            return [
                'id' => (string) $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_system' => (bool) $role->is_system,
                'permissions' => $permissionKeys,
                'created_at' => $role->created_at?->toIso8601String(),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function configurationSchemas(): array
    {
        $response = $this->configurationController->platformSchemas();
        $payload = $response->getData(true);

        return is_array($payload['data'] ?? null) ? $payload['data'] : [];
    }
}
