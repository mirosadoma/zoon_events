<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Subscriptions\Infrastructure\Persistence\Models\TenantSubscription;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;

class TenantSubscriptionController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contextStore,
    ) {}

    public function index()
    {
        $context = $this->contextStore->current();

        $subscriptions = TenantSubscription::query()
            ->where('tenant_id', $context->tenant->id)
            ->with('plan')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TenantSubscription $sub) => $this->mapSubscription($sub));

        return $this->success($subscriptions->all());
    }

    public function current()
    {
        $context = $this->contextStore->current();

        $subscription = TenantSubscription::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->with('plan')
            ->latest('starts_at')
            ->first();

        if (! $subscription) {
            return $this->success(null);
        }

        return $this->success($this->mapSubscription($subscription));
    }

    public function renew(Request $request, TenantSubscription $subscription)
    {
        $plan = $subscription->plan;
        abort_if(! $plan, 404, 'Plan not found.');

        $startsAt = $subscription->ends_at->isFuture() ? $subscription->ends_at : now();

        $newSubscription = TenantSubscription::query()->create([
            'tenant_id' => $subscription->tenant_id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays($plan->duration_days),
            'amount_paid' => $plan->price,
            'payment_reference' => 'RENEWAL-' . now()->format('YmdHis'),
            'payment_method' => 'admin_renewal',
            'created_by_user_id' => $request->user()?->id,
        ]);

        return $this->success($this->mapSubscription($newSubscription->load('plan')), 201);
    }

    private function mapSubscription(TenantSubscription $sub): array
    {
        return [
            'id' => $sub->id,
            'plan' => $sub->plan ? [
                'id' => $sub->plan->id,
                'name' => $sub->plan->name,
                'name_ar' => $sub->plan->name_ar,
                'is_trial' => $sub->plan->is_trial,
            ] : null,
            'status' => $sub->status->value,
            'starts_at' => $sub->starts_at?->toIso8601String(),
            'ends_at' => $sub->ends_at?->toIso8601String(),
            'amount_paid' => $sub->amount_paid,
            'payment_reference' => $sub->payment_reference,
            'created_at' => $sub->created_at?->toIso8601String(),
        ];
    }
}
