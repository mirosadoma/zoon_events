<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PlatformUserController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function index(): JsonResponse
    {
        $users = User::query()
            ->whereHas('platformAssignments')
            ->with('platformAssignments')
            ->latest()
            ->limit(100)
            ->get();

        $data = $users->map(fn (User $user): array => $this->mapUser($user));

        return $this->success($data->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role_id' => ['required', 'exists:platform_roles,id'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $user = DB::transaction(function () use ($validated, $actor): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'status' => LifecycleStatus::Active,
                'preferred_locale' => 'en',
                'created_by_user_id' => $actor->id,
            ]);

            DB::table('platform_role_assignments')->insert([
                'user_id' => $user->id,
                'platform_role_id' => $validated['role_id'],
                'granted_by_user_id' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $user;
        });

        $this->audit->writePlatform(
            'platform_user.created',
            'succeeded',
            $actor,
            targetType: 'user',
            targetId: $user->id,
            metadata: ['email' => $validated['email']],
        );

        return $this->success($this->mapUser($user->refresh()->load('platformAssignments')), 201);
    }

    public function update(Request $request, string $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'email' => ['sometimes', 'email', 'max:254', "unique:users,email,{$user->id}"],
            'role_id' => ['sometimes', 'exists:platform_roles,id'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        DB::transaction(function () use ($user, $validated, $actor): void {
            $user->update(array_filter([
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
            ]));

            if (isset($validated['role_id'])) {
                DB::table('platform_role_assignments')
                    ->where('user_id', $user->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now(), 'revoked_by_user_id' => $actor->id]);

                DB::table('platform_role_assignments')->insert([
                    'user_id' => $user->id,
                    'platform_role_id' => $validated['role_id'],
                    'granted_by_user_id' => $actor->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->audit->writePlatform(
            'platform_user.updated',
            'succeeded',
            $actor,
            targetType: 'user',
            targetId: $user->id,
        );

        return $this->success($this->mapUser($user->refresh()->load('platformAssignments')));
    }

    public function destroy(Request $request, string $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);

        /** @var User $actor */
        $actor = $request->user();

        abort_if($user->id === $actor->id, 403, 'Cannot delete yourself.');

        $this->audit->writePlatform(
            'platform_user.deleted',
            'succeeded',
            $actor,
            targetType: 'user',
            targetId: $user->id,
            metadata: ['email' => $user->email],
        );

        DB::transaction(function () use ($user): void {
            DB::table('platform_role_assignments')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return $this->success(null, 204);
    }

    private function mapUser(User $user): array
    {
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
    }
}
