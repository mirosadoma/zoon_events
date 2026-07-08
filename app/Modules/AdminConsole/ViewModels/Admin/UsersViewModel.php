<?php

namespace App\Modules\AdminConsole\ViewModels\Admin;

use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Support\Collection;

final readonly class UsersViewModel
{
    /**
     * @param  Collection<int, TenantMembership>  $memberships
     * @return array{tenantId: string, users: list<array<string, mixed>>}
     */
    public function index(string $tenantId, Collection $memberships): array
    {
        return [
            'tenantId' => $tenantId,
            'users' => $memberships->map(fn (TenantMembership $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'status' => $membership->status->value,
                'user_status' => $membership->user->status->value,
                'created_at' => $membership->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
