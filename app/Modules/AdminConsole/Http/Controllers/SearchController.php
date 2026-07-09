<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\MembershipVisibility;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
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
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $user = $request->user();

        if ($user === null || mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];
        $context = $this->contexts->currentOrNull()
            ?? $this->sessionContext->tenantContextFor($user);

        if ($context !== null && $this->permissions->hasTenantPermission($context, 'event.view')) {
            $events = Event::query()
                ->where('tenant_id', $context->tenant->id)
                ->where(function ($builder) use ($query): void {
                    $builder
                        ->where('name_en', 'like', "%{$query}%")
                        ->orWhere('name_ar', 'like', "%{$query}%")
                        ->orWhere('slug', 'like', "%{$query}%");
                })
                ->orderBy('name_en')
                ->limit(8)
                ->get(['id', 'name_en', 'name_ar', 'slug', 'status']);

            foreach ($events as $event) {
                $results[] = [
                    'type' => 'event',
                    'id' => (string) $event->id,
                    'label' => $event->name_en,
                    'label_ar' => $event->name_ar,
                    'href' => "/tenant/events/{$event->id}",
                    'meta' => $event->status,
                ];
            }
        }

        if ($this->permissions->hasPlatformPermission($user, 'platform.user.view')) {
            $users = User::query()
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
                    'href' => '/platform/users',
                    'meta' => $row->email,
                ];
            }
        } elseif ($context !== null && $this->permissions->hasTenantPermission($context, 'membership.view')) {
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
}
