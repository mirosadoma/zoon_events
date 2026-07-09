<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Application\Queries\SearchAuditLogs;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Tenancy\Http\Controllers\ConfigurationController;
use App\Modules\FeatureFlags\Infrastructure\Persistence\Models\FeatureFlag;
use App\Modules\Operations\Application\Health\HealthService;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
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

    public function show(string $section): Response
    {
        $permission = [
            'tenants' => 'platform.tenant.view',
            'users' => 'platform.user.view',
            'roles' => 'platform.role.view',
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
            'users' => $section === 'tenants' ? User::query()->orderBy('name')->limit(200)->get(['id', 'name', 'email'])->all() : [],
        ]);
    }

    public function configuration(): Response
    {
        return $this->show('configuration');
    }

    private function canManage(string $section): bool
    {
        return match ($section) {
            'tenants' => Gate::allows('platform.tenant.manage'),
            'users' => Gate::allows('platform.user.manage'),
            'roles' => Gate::allows('platform.role.manage'),
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
            'tenants' => Tenant::query()->latest()->limit(100)->get()->map(fn (Tenant $tenant): array => [
                'id' => (string) $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status->value,
                'default_locale' => $tenant->default_locale,
                'timezone' => $tenant->timezone,
                'created_at' => $tenant->created_at?->toIso8601String(),
            ])->all(),
            'users' => User::query()->latest()->limit(100)->get()->map(fn (User $user): array => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status->value,
                'preferred_locale' => $user->preferred_locale,
                'created_at' => $user->created_at?->toIso8601String(),
            ])->all(),
            'roles' => PlatformRole::query()->with('permissions')->orderBy('name')->get()->map(fn (PlatformRole $role): array => [
                'id' => (string) $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_system' => (bool) $role->is_system,
                'permissions' => $role->permissions->pluck('key')->values()->all(),
            ])->all(),
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
