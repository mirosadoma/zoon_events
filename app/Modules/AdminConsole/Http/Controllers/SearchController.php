<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\MembershipVisibility;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Application\Support\EventMediaPresenter;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchController extends Controller
{
    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly SessionContextBuilder $sessionContext,
        private readonly PermissionEvaluator $permissions,
        private readonly MembershipVisibility $membershipVisibility,
        private readonly EventMediaPresenter $media,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $user = $request->user();

        if ($user === null || mb_strlen($query) < 1) {
            return response()->json(['results' => []]);
        }

        $results = [];
        $context = $this->resolveTenantContext($request, $user);
        $platformWide = $this->canSearchEventsPlatformWide($user);
        $searchableTenantIds = $this->searchableTenantIds($user, $context, $platformWide);
        $tenantNames = $platformWide
            ? Tenant::query()->whereIn('id', $searchableTenantIds)->pluck('name', 'id')
            : collect();

        if ($searchableTenantIds !== []) {
            $events = Event::query()
                ->whereIn('tenant_id', $searchableTenantIds)
                ->where(function ($builder) use ($query): void {
                    $builder
                        ->where('name_en', 'like', "%{$query}%")
                        ->orWhere('name_ar', 'like', "%{$query}%")
                        ->orWhere('slug', 'like', "%{$query}%");
                })
                ->orderBy('name_en')
                ->limit(12)
                ->get(['id', 'tenant_id', 'name_en', 'name_ar', 'slug', 'status', 'main_image_path']);

            foreach ($events as $event) {
                $result = [
                    'type' => 'event',
                    'id' => (string) $event->id,
                    'label' => $event->name_en,
                    'label_ar' => $event->name_ar,
                    'href' => "/tenant/events/{$event->id}",
                    'meta' => $event->status,
                    'main_image' => $this->media->url($event->main_image_path),
                ];

                if ($platformWide) {
                    $result['tenant_name'] = (string) ($tenantNames[$event->tenant_id] ?? '');
                }

                $results[] = $result;
            }
        }

        if ($context !== null && $this->permissions->hasTenantPermission($context, 'membership.view')) {
            $visibleUserIds = $this->membershipVisibility
                ->scopeVisibleMemberships(TenantMembership::query(), $context, $user)
                ->pluck('user_id');

            $users = User::query()
                ->whereIn('id', $visibleUserIds)
                ->where(function ($builder) use ($query): void {
                    $builder
                        ->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                })
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'email']);

            foreach ($users as $row) {
                $results[] = [
                    'type' => 'user',
                    'id' => (string) $row->id,
                    'label' => $row->name,
                    'href' => '/admin/users?q='.urlencode($row->email),
                    'meta' => $row->email,
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    private function resolveTenantContext(Request $request, User $user): ?TenantContext
    {
        $tenantId = trim((string) $request->headers->get(config('tenancy.tenant_header', 'X-Tenant-ID'), ''));

        if ($tenantId !== '') {
            $membership = TenantMembership::query()
                ->with('tenant')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->where('status', LifecycleStatus::Active)
                ->whereHas('tenant', fn ($query) => $query->where('status', LifecycleStatus::Active))
                ->first();

            if ($membership instanceof TenantMembership) {
                return new TenantContext($membership->tenant, $membership, $user);
            }
        }

        return $this->contexts->currentOrNull()
            ?? $this->sessionContext->tenantContextFor($user);
    }

    private function canSearchEventsPlatformWide(User $user): bool
    {
        return $this->permissions->hasPlatformPermission($user, 'platform.tenant.view')
            || $this->permissions->hasPlatformPermission($user, 'platform.tenant.manage');
    }

    /**
     * @return list<int|string>
     */
    private function searchableTenantIds(User $user, ?TenantContext $preferred, bool $platformWide): array
    {
        if ($platformWide) {
            $tenantIds = Tenant::query()
                ->where('status', LifecycleStatus::Active)
                ->orderBy('name')
                ->pluck('id')
                ->all();

            if ($preferred !== null) {
                $preferredId = $preferred->tenant->id;
                $tenantIds = array_values(array_unique([
                    $preferredId,
                    ...array_values(array_filter(
                        $tenantIds,
                        static fn ($tenantId): bool => $tenantId !== $preferredId,
                    )),
                ]));
            }

            return $tenantIds;
        }

        $tenantIds = [];

        if ($preferred !== null && $this->permissions->hasTenantPermission($preferred, 'event.view')) {
            $tenantIds[] = $preferred->tenant->id;
        }

        $memberships = TenantMembership::query()
            ->with('tenant')
            ->where('user_id', $user->id)
            ->where('status', LifecycleStatus::Active)
            ->whereHas('tenant', fn ($query) => $query->where('status', LifecycleStatus::Active))
            ->orderBy('created_at')
            ->get();

        foreach ($memberships as $membership) {
            $context = new TenantContext($membership->tenant, $membership, $user);

            if (! $this->permissions->hasTenantPermission($context, 'event.view')) {
                continue;
            }

            if (! in_array($membership->tenant_id, $tenantIds, true)) {
                $tenantIds[] = $membership->tenant_id;
            }
        }

        return $tenantIds;
    }
}
