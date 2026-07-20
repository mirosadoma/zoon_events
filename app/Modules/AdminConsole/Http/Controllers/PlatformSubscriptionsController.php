<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Infrastructure\Persistence\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Infrastructure\Persistence\Models\TenantSubscription;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformSubscriptionsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('platform.subscription.view');

        return Inertia::render('platform/subscriptions/Index', [
            'canManage' => Gate::allows('platform.subscription.manage'),
            'plans' => $this->plans(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('platform.subscription.manage');

        return Inertia::render('platform/subscriptions/Form', [
            'canManage' => true,
            'plan' => null,
        ]);
    }

    public function edit(string $locale, string $plan): Response
    {
        unset($locale);
        Gate::authorize('platform.subscription.manage');

        $model = SubscriptionPlan::query()->findOrFail($plan);

        return Inertia::render('platform/subscriptions/Form', [
            'canManage' => true,
            'plan' => $this->mapPlan($model),
        ]);
    }

    public function show(string $locale, string $plan): Response
    {
        unset($locale);
        Gate::authorize('platform.subscription.view');

        $model = SubscriptionPlan::query()->findOrFail($plan);

        $subscriptions = TenantSubscription::query()
            ->with('tenant')
            ->where('plan_id', $model->id)
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (TenantSubscription $sub): array => [
                'id' => (string) $sub->id,
                'tenant_name' => $sub->tenant?->name ?? '—',
                'tenant_id' => (string) $sub->tenant_id,
                'status' => $sub->status->value,
                'starts_at' => $sub->starts_at?->toIso8601String(),
                'ends_at' => $sub->ends_at?->toIso8601String(),
                'amount_paid' => $sub->amount_paid,
                'created_at' => $sub->created_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('platform/subscriptions/Show', [
            'canManage' => Gate::allows('platform.subscription.manage'),
            'plan' => $this->mapPlan($model),
            'subscriptions' => $subscriptions,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function plans(): array
    {
        return SubscriptionPlan::query()
            ->orderBy('sort_order')
            ->get()
            ->map(function (SubscriptionPlan $plan): array {
                $mapped = $this->mapPlan($plan);
                $mapped['tenant_count'] = TenantSubscription::query()
                    ->where('plan_id', $plan->id)
                    ->where('status', 'active')
                    ->count();

                return $mapped;
            })
            ->all();
    }

    /** @return array<string, mixed> */
    private function mapPlan(SubscriptionPlan $plan): array
    {
        return [
            'id' => (string) $plan->id,
            'name' => $plan->name,
            'name_ar' => $plan->name_ar,
            'description' => $plan->description,
            'description_ar' => $plan->description_ar,
            'is_trial' => $plan->is_trial,
            'is_active' => $plan->is_active,
            'duration_days' => $plan->duration_days,
            'price' => $plan->price,
            'currency' => $plan->currency,
            'max_events' => $plan->max_events,
            'max_attendees' => $plan->max_attendees,
            'max_devices' => $plan->max_devices,
            'created_at' => $plan->created_at?->toIso8601String(),
        ];
    }
}
