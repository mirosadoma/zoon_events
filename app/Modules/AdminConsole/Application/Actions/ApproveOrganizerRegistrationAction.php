<?php

namespace App\Modules\AdminConsole\Application\Actions;

use App\Models\User;
use App\Modules\AdminConsole\Application\SiteSettingsRepository;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\OrganizerRegistrationRequest;
use App\Modules\AdminConsole\Mail\OrganizerApprovedMail;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Application\Actions\CreateTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ApproveOrganizerRegistrationAction
{
    public function __construct(
        private readonly CreateTenant $createTenant,
    ) {}

    public function execute(OrganizerRegistrationRequest $request, User $reviewer): OrganizerRegistrationRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This request was already reviewed.']);
        }

        if (User::query()->where('email', $request->email)->exists()) {
            throw ValidationException::withMessages(['email' => 'A user with this email already exists.']);
        }

        return DB::transaction(function () use ($request, $reviewer): OrganizerRegistrationRequest {
            $user = User::query()->create([
                'name' => $request->name,
                'email' => mb_strtolower($request->email),
                'password' => $request->password_hash,
                'status' => LifecycleStatus::Active->value,
                'preferred_locale' => 'en',
                'created_by_user_id' => $reviewer->id,
            ]);

            $slug = $this->uniqueSlug(Str::slug($request->organization_name));

            $tenant = $this->createTenant->handle([
                'name' => $request->organization_name,
                'slug' => $slug,
                'default_locale' => 'en',
                'timezone' => 'Africa/Cairo',
                'data_residency_region' => 'eg',
                'initial_admin_user_id' => $user->id,
                'reason' => 'Organizer registration approved',
            ], $reviewer);

            $request->update([
                'status' => 'approved',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'created_user_id' => $user->id,
                'created_tenant_id' => $tenant->id,
            ]);

            $settings = app(SiteSettingsRepository::class)->current();

            Mail::to($user->email)->send(new OrganizerApprovedMail(
                organizerName: $user->name,
                organizationName: $request->organization_name,
                loginUrl: url('/login'),
                appName: $settings->app_name_en,
                supportEmail: $settings->support_email ?? config('mail.from.address'),
            ));

            return $request->fresh();
        });
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base !== '' ? $base : 'organizer';
        $candidate = $slug;
        $suffix = 1;

        while (\App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant::query()->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
