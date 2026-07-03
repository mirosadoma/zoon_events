<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Identity\Application\AuthenticateUser;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly AuthenticateUser $authenticate,
    ) {}

    public function issueToken(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
            'device_name' => ['required', 'string', 'min:1', 'max:120'],
        ]);

        try {
            $user = $this->authenticate->attempt($validated['email'], $validated['password']);
        } catch (FoundationException $exception) {
            $known = User::query()->where('email', mb_strtolower($validated['email']))->first();
            $this->audit->write(
                'platform',
                null,
                'auth.failed',
                'failed',
                $known,
                $exception->problemCode === 'user_inactive' ? 'user_inactive' : 'invalid_credentials',
                targetType: $known ? 'user' : null,
                targetId: $known?->id,
                metadata: ['identity_fingerprint' => hash('sha256', mb_strtolower($validated['email']))],
            );
            throw $exception;
        }

        $token = DB::transaction(function () use ($user, $validated) {
            $token = $user->createToken($validated['device_name'], ['api']);
            $user->forceFill(['last_authenticated_at' => now()])->save();
            $this->audit->writePlatform('auth.token.issued', 'succeeded', $user, targetType: 'user', targetId: $user->id);

            return $token;
        });

        return $this->success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => null,
            'user' => $this->mapUser($user),
        ]);
    }

    public function revokeCurrentToken(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $request->user()?->currentAccessToken()?->delete();
        $this->audit->writePlatform('auth.token.revoked', 'succeeded', $user, targetType: 'user', targetId: $user->id);

        return $this->empty();
    }

    public function me(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success($this->mapUser($user));
    }

    public function tenants(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $memberships = TenantMembership::query()
            ->with('tenant')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        return $this->success(
            $memberships->map(fn (TenantMembership $membership): array => [
                'tenant' => [
                    'id' => $membership->tenant->id,
                    'name' => $membership->tenant->name,
                    'slug' => $membership->tenant->slug,
                    'status' => $membership->tenant->status->value,
                    'default_locale' => $membership->tenant->default_locale,
                    'timezone' => $membership->tenant->timezone,
                    'data_residency_region' => $membership->tenant->data_residency_region,
                    'created_at' => $membership->tenant->created_at?->toIso8601String(),
                ],
                'membership_id' => $membership->id,
            ])->values()->all(),
        );
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
