<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Shared\Application\Pagination\CursorPaginator;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantMembershipController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contextStore,
        private readonly AuditWriter $audit,
        private readonly CursorPaginator $paginator,
    ) {}

    public function index(Request $request)
    {
        $context = $this->contextStore->current();
        $page = $this->paginator->paginate(TenantMembership::query()
            ->with('user')
            ->where('tenant_id', $context->tenant->id), "tenant:{$context->tenant->id}:memberships", [], $request->string('cursor')->toString(), $request->integer('page_size', 50));

        return $this->success(
            collect($page->items)->map(fn (TenantMembership $membership): array => $this->mapMembership($membership))->all(),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function store(Request $request)
    {
        $context = $this->contextStore->current();
        $validated = $request->validate([
            'user_id' => ['required_without_all:name,email,password', 'nullable', 'exists:users,id'],
            'name' => ['required_without:user_id', 'nullable', 'string', 'max:160'],
            'email' => ['required_without:user_id', 'nullable', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required_without:user_id', 'nullable', 'string', 'min:6', 'max:1024'],
            'preferred_locale' => ['required_without:user_id', 'nullable', 'in:en,ar'],
        ]);

        $membership = DB::transaction(function () use ($context, $validated): TenantMembership {
            $userId = isset($validated['user_id']) ? (int) $validated['user_id'] : null;

            if ($userId === null) {
                $user = User::query()->create([
                    'name' => $validated['name'],
                    'email' => mb_strtolower((string) $validated['email']),
                    'password' => Hash::make((string) $validated['password']),
                    'status' => LifecycleStatus::Active->value,
                    'preferred_locale' => $validated['preferred_locale'] ?? 'en',
                    'created_by_user_id' => $context->actor->id,
                ]);
                $userId = $user->id;
            }

            if (TenantMembership::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('user_id', $userId)
                ->exists()) {
                throw FoundationException::conflict('membership_exists', 'This user is already a member of the tenant.');
            }

            $membership = TenantMembership::query()->create([
                'tenant_id' => $context->tenant->id,
                'user_id' => $userId,
                'status' => LifecycleStatus::Active->value,
                'created_by_user_id' => $context->actor->id,
            ]);
            $this->audit->writeTenant('membership.created', 'succeeded', $context, targetType: 'membership', targetId: $membership->id);

            return $membership;
        });

        return $this->success($this->mapMembership($membership->load('user')), 201);
    }

    public function update(Request $request, string $membershipId)
    {
        $context = $this->contextStore->current();
        $membership = TenantMembership::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($membershipId);

        $validated = $request->validate([
            'status' => ['required', 'in:active,suspended,deactivated'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['status'] !== LifecycleStatus::Active->value && $this->isLastActiveAdministrator($membership)) {
            throw FoundationException::conflict('last_tenant_administrator', 'The final active tenant administrator cannot be removed.');
        }

        DB::transaction(function () use ($membership, $validated, $context): void {
            $membership->status = $validated['status'];
            $membership->suspended_at = $validated['status'] === LifecycleStatus::Suspended->value ? now() : null;
            $membership->deactivated_at = $validated['status'] === LifecycleStatus::Deactivated->value ? now() : null;
            $membership->save();
            $this->audit->writeTenant('membership.updated', 'succeeded', $context, targetType: 'membership', targetId: $membership->id, metadata: ['reason' => $validated['reason'] ?? null]);
        });

        return $this->success($this->mapMembership($membership->load('user')));
    }

    private function mapMembership(TenantMembership $membership): array
    {
        return [
            'id' => (string) $membership->id,
            'tenant_id' => (string) $membership->tenant_id,
            'user' => [
                'id' => (string) $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'status' => $membership->user->status->value,
                'preferred_locale' => $membership->user->preferred_locale,
                'created_at' => $membership->user->created_at?->toIso8601String(),
            ],
            'status' => $membership->status->value,
            'created_at' => $membership->created_at?->toIso8601String(),
        ];
    }

    private function isLastActiveAdministrator(TenantMembership $membership): bool
    {
        $hasAdministratorRole = DB::table('tenant_role_assignments as assignments')
            ->join('tenant_roles as roles', function ($join): void {
                $join->on('roles.id', '=', 'assignments.tenant_role_id')
                    ->on('roles.tenant_id', '=', 'assignments.tenant_id');
            })
            ->where('assignments.tenant_id', $membership->tenant_id)
            ->where('assignments.tenant_membership_id', $membership->id)
            ->where('roles.is_system', true)
            ->where('roles.name', 'Tenant Administrator')
            ->whereNull('assignments.revoked_at')
            ->where(fn ($query) => $query->whereNull('assignments.expires_at')->orWhere('assignments.expires_at', '>', now()))
            ->exists();

        if (! $hasAdministratorRole) {
            return false;
        }

        return ! DB::table('tenant_role_assignments as assignments')
            ->join('tenant_roles as roles', function ($join): void {
                $join->on('roles.id', '=', 'assignments.tenant_role_id')
                    ->on('roles.tenant_id', '=', 'assignments.tenant_id');
            })
            ->join('tenant_memberships as memberships', function ($join): void {
                $join->on('memberships.id', '=', 'assignments.tenant_membership_id')
                    ->on('memberships.tenant_id', '=', 'assignments.tenant_id');
            })
            ->where('assignments.tenant_id', $membership->tenant_id)
            ->where('assignments.tenant_membership_id', '!=', $membership->id)
            ->where('memberships.status', 'active')
            ->where('roles.is_system', true)
            ->where('roles.name', 'Tenant Administrator')
            ->whereNull('assignments.revoked_at')
            ->where(fn ($query) => $query->whereNull('assignments.expires_at')->orWhere('assignments.expires_at', '>', now()))
            ->exists();
    }
}
