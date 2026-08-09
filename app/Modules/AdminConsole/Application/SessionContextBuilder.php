<?php

namespace App\Modules\AdminConsole\Application;

use App\Models\User;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Authorization\Domain\PermissionCatalog;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRoleAssignment;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRoleAssignment;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use App\Modules\Tenancy\Infrastructure\Persistence\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class SessionContextBuilder
{
    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly PermissionEvaluator $evaluator,
    ) {}

    /**
     * @return array{session: ?array<string, mixed>, can: array<string, bool>, permissions: list<string>}
     */
    public function build(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ['session' => null, 'can' => [], 'permissions' => []];
        }

        $console = $this->resolveConsole($user);
        $context = $console === 'platform' ? null : $this->resolveContext($user);
        $can = $this->buildPermissionMap($user, $context, $console);
        $permissions = array_keys(array_filter($can));

        return [
            'session' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'type' => $user->type ?? 'staff',
                    'is_visitor' => $user->isVisitor(),
                    'role_label' => $this->resolveRoleLabel($user, $context, $console),
                    'phone' => null,
                    'last_login_at' => $user->last_authenticated_at?->toIso8601String(),
                ],
                'tenant' => $context !== null ? [
                    'id' => $context->tenant->id,
                    'name' => $context->tenant->name,
                    'slug' => $context->tenant->slug,
                    'branding' => $context->tenant->policy_profile,
                    'default_locale' => $context->tenant->default_locale,
                    'default_timezone' => $context->tenant->timezone,
                ] : null,
                'locale' => app()->getLocale(),
                'theme' => $request->cookie('theme', 'system'),
                'role_label' => $this->resolveRoleLabel($user, $context, $console),
                'user_type' => $user->type ?? 'staff',
                'is_visitor' => $user->isVisitor(),
                'console' => $console,
            ],
            'can' => $can,
            'permissions' => $permissions,
        ];
    }

    public function tenantContextFor(User $user): ?TenantContext
    {
        if ($this->hasActivePlatformRole($user)) {
            return null;
        }

        return $this->resolveContext($user);
    }

    /**
     * @return 'platform'|'organizer'
     */
    public function resolveConsole(User $user): string
    {
        return $this->hasActivePlatformRole($user) ? 'platform' : 'organizer';
    }

    public function hasActivePlatformRole(User $user): bool
    {
        return PlatformRoleAssignment::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', CarbonImmutable::now());
            })
            ->exists();
    }

    /**
     * First permitted platform console home path (locale-agnostic).
     */
    public function platformHomePath(User $user): string
    {
        $candidates = [
            ['permission' => 'platform.tenant.view', 'href' => '/platform/tenants'],
            ['permission' => 'platform.user.view', 'href' => '/platform/users'],
            ['permission' => 'platform.role.view', 'href' => '/platform/roles'],
            ['permission' => 'platform.event.view', 'href' => '/platform/all-events'],
            ['permission' => 'platform.subscription.view', 'href' => '/platform/subscriptions'],
            ['permission' => 'platform.configuration.view', 'href' => '/platform/site-settings'],
            ['permission' => 'platform.audit.view', 'href' => '/platform/audit'],
            ['permission' => 'operations.health.view', 'href' => '/platform/health'],
            ['permission' => 'platform.feature_flag.view', 'href' => '/platform/feature-flags'],
        ];

        foreach ($candidates as $candidate) {
            if ($this->evaluator->hasPlatformPermission($user, $candidate['permission'])) {
                return $candidate['href'];
            }
        }

        return '/platform/tenants';
    }

    private function resolveContext(User $user): ?TenantContext
    {
        $bound = $this->contexts->currentOrNull();

        if ($bound !== null) {
            return $bound;
        }

        $membership = TenantMembership::query()
            ->with('tenant')
            ->where('user_id', $user->id)
            ->where('status', LifecycleStatus::Active)
            ->whereHas('tenant', fn ($query) => $query
                ->where('status', LifecycleStatus::Active)
                ->where('is_active', true))
            ->orderBy('created_at')
            ->first();

        if (! $membership instanceof TenantMembership) {
            return null;
        }

        return $this->contexts->bind($membership->tenant, $membership, $user);
    }

    /**
     * @param  'platform'|'organizer'  $console
     * @return array<string, bool>
     */
    private function buildPermissionMap(User $user, ?TenantContext $context, string $console): array
    {
        $can = [];

        foreach (PermissionCatalog::all() as $definition) {
            $key = $definition['key'];

            if ($definition['scope'] === 'platform') {
                $can[$key] = $console === 'platform'
                    && $this->evaluator->hasPlatformPermission($user, $key);
            } else {
                $can[$key] = $console === 'organizer'
                    && $context !== null
                    && $this->evaluator->hasTenantPermission($context, $key);
            }
        }

        return $can;
    }

    /**
     * @param  'platform'|'organizer'  $console
     */
    private function resolveRoleLabel(User $user, ?TenantContext $context, string $console): string
    {
        if ($console === 'platform') {
            $platformAssignment = PlatformRoleAssignment::query()
                ->with('role')
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', CarbonImmutable::now());
                })
                ->first();

            return $platformAssignment?->role?->name ?? 'Operator';
        }

        if ($context !== null) {
            $assignment = TenantRoleAssignment::query()
                ->withoutGlobalScope(TenantScope::class)
                ->with(['role' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)])
                ->where('tenant_membership_id', $context->membership->id)
                ->where('tenant_id', $context->tenant->id)
                ->whereNull('revoked_at')
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', CarbonImmutable::now());
                })
                ->first();

            if ($assignment?->role !== null) {
                return $assignment->role->name;
            }
        }

        return 'Operator';
    }
}
