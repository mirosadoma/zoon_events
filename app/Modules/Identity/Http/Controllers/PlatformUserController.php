<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Shared\Application\Pagination\CursorPaginator;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PlatformUserController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly CursorPaginator $paginator,
    ) {}

    public function index(Request $request)
    {
        $page = $this->paginator->paginate(User::query(), 'platform:users', [], $request->string('cursor')->toString(), $request->integer('page_size', 50));

        return $this->success(
            collect($page->items)->map(fn (User $user): array => $this->mapUser($user))->all(),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:1024'],
            'preferred_locale' => ['required', 'in:en,ar'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $user = DB::transaction(function () use ($validated, $actor): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'password' => Hash::make($validated['password']),
                'status' => LifecycleStatus::Active->value,
                'preferred_locale' => $validated['preferred_locale'],
                'created_by_user_id' => $actor->id,
            ]);
            $this->audit->writePlatform('user.provisioned', 'succeeded', $actor, targetType: 'user', targetId: $user->id, metadata: ['reason' => $validated['reason']]);

            return $user;
        });

        return $this->success($this->mapUser($user), 201);
    }

    public function update(Request $request, string $userId)
    {
        $user = User::query()->findOrFail($userId);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'status' => ['sometimes', 'in:active,suspended,deactivated'],
            'preferred_locale' => ['sometimes', 'in:en,ar'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        DB::transaction(function () use ($user, $validated, $actor): void {
            $user->fill(collect($validated)->except('reason')->all());
            if (($validated['status'] ?? null) === LifecycleStatus::Active->value) {
                $user->suspended_at = null;
                $user->deactivated_at = null;
            } elseif (($validated['status'] ?? null) === LifecycleStatus::Suspended->value) {
                $user->suspended_at = now();
                $user->deactivated_at = null;
                $user->tokens()->delete();
            } elseif (($validated['status'] ?? null) === LifecycleStatus::Deactivated->value) {
                $user->deactivated_at = now();
                $user->tokens()->delete();
                $user->memberships()->where('status', 'active')->update(['status' => 'deactivated', 'deactivated_at' => now()]);
            }
            $user->save();
            $this->audit->writePlatform('user.updated', 'succeeded', $actor, targetType: 'user', targetId: $user->id, metadata: ['reason' => $validated['reason']]);
        });

        return $this->success($this->mapUser($user->refresh()));
    }

    private function mapUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
            'preferred_locale' => $user->preferred_locale,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
