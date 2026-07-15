<?php

namespace App\Modules\AdminConsole\Application;

use App\Models\User;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRoleAssignment;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRoleAssignment;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use App\Modules\Tenancy\Infrastructure\Persistence\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
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

        $context = $this->resolveContext($user);
        $can = $this->buildPermissionMap($user, $context);
        $permissions = array_keys(array_filter($can));

        return [
            'session' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_label' => $this->resolveRoleLabel($user, $context),
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
                'role_label' => $this->resolveRoleLabel($user, $context),
            ],
            'can' => $can,
            'permissions' => $permissions,
        ];
    }

    public function tenantContextFor(User $user): ?TenantContext
    {
        return $this->resolveContext($user);
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
            ->whereHas('tenant', fn ($query) => $query->where('status', LifecycleStatus::Active))
            ->orderBy('created_at')
            ->first();

        if (! $membership instanceof TenantMembership) {
            return null;
        }

        return $this->contexts->bind($membership->tenant, $membership, $user);
    }

    /**
     * @return array<string, bool>
     */
    private function buildPermissionMap(User $user, ?TenantContext $context): array
    {
        $can = [];

        foreach (PermissionSeeder::definitions() as $definition) {
            $key = $definition['key'];

            if ($definition['scope'] === 'platform') {
                $can[$key] = $this->evaluator->hasPlatformPermission($user, $key);
            } else {
                $can[$key] = $context !== null
                    && $this->evaluator->hasTenantPermission($context, $key);
            }
        }

        return $can;
    }

    private function resolveRoleLabel(User $user, ?TenantContext $context): string
    {
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
}
