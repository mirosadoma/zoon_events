<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Subscriptions\Infrastructure\Persistence\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request)
    {
        $plans = SubscriptionPlan::query()
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => $this->mapPlan($plan));

        return $this->success($plans->all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'is_trial' => ['boolean'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'max_events' => ['nullable', 'integer', 'min:0'],
            'max_attendees' => ['nullable', 'integer', 'min:0'],
            'max_devices' => ['nullable', 'integer', 'min:0'],
            'allowed_features' => ['nullable', 'array'],
            'allowed_features.*' => ['string'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $validated['created_by_user_id'] = $request->user()->id;

        if (($validated['is_trial'] ?? false) === true) {
            $validated['price'] = 0;
        }

        $plan = SubscriptionPlan::query()->create($validated);

        return $this->success($this->mapPlan($plan), 201);
    }

    public function show(SubscriptionPlan $plan)
    {
        return $this->success($this->mapPlan($plan));
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'is_trial' => ['boolean'],
            'is_active' => ['boolean'],
            'duration_days' => ['sometimes', 'integer', 'min:1'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'max_events' => ['nullable', 'integer', 'min:0'],
            'max_attendees' => ['nullable', 'integer', 'min:0'],
            'max_devices' => ['nullable', 'integer', 'min:0'],
            'allowed_features' => ['nullable', 'array'],
            'allowed_features.*' => ['string'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $plan->fill($validated)->save();

        return $this->success($this->mapPlan($plan->refresh()));
    }

    public function destroy(SubscriptionPlan $plan)
    {
        if ($plan->subscriptions()->where('status', 'active')->exists()) {
            abort(409, 'Cannot delete a plan with active subscriptions.');
        }

        $plan->delete();

        return $this->empty();
    }

    /** Public endpoint: list active plans for organizer registration */
    public function publicIndex()
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => $this->mapPlan($plan));

        return $this->success($plans->all());
    }

    private function mapPlan(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
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
            'allowed_features' => $plan->allowed_features ?? [],
            'sort_order' => $plan->sort_order,
            'created_at' => $plan->created_at?->toIso8601String(),
        ];
    }
}
