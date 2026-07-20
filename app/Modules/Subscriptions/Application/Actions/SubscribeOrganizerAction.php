<?php

namespace App\Modules\Subscriptions\Application\Actions;

use App\Models\User;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Subscriptions\Infrastructure\Persistence\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Infrastructure\Persistence\Models\TenantSubscription;
use App\Modules\Tenancy\Application\Actions\CreateTenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class SubscribeOrganizerAction
{
    public function __construct(
        private readonly CreateTenant $createTenant,
    ) {}

    /**
     * @return array{user: User, tenant: Tenant, subscription: TenantSubscription}
     */
    public function execute(array $data, SubscriptionPlan $plan): array
    {
        return DB::transaction(function () use ($data, $plan): array {
            $password = $data['password'] ?? Str::random(12);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'password' => Hash::make($password),
                'status' => LifecycleStatus::Active->value,
                'preferred_locale' => $data['locale'] ?? 'en',
            ]);

            $slug = $this->uniqueSlug(Str::slug($data['organization_name']));

            $tenant = $this->createTenant->handle([
                'name' => $data['organization_name'],
                'slug' => $slug,
                'organization_type' => 'organizer',
                'default_locale' => $data['locale'] ?? 'en',
                'timezone' => $data['timezone'] ?? 'Africa/Cairo',
                'data_residency_region' => $data['region'] ?? 'eg',
                'initial_admin_user_id' => $user->id,
                'reason' => $plan->is_trial ? 'Trial subscription' : 'Paid subscription',
            ], $user);

            $subscription = TenantSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addDays($plan->duration_days),
                'amount_paid' => $plan->price,
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'created_by_user_id' => $user->id,
            ]);

            Mail::to($user->email)->send(new \App\Modules\Subscriptions\Mail\OrganizerCredentialsMail(
                name: $user->name,
                email: $user->email,
                password: $password,
                organizationName: $data['organization_name'],
                planName: $plan->name,
                loginUrl: url('/login'),
            ));

            return compact('user', 'tenant', 'subscription');
        });
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base !== '' ? $base : 'organizer';
        $candidate = $slug;
        $suffix = 1;

        while (Tenant::query()->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
