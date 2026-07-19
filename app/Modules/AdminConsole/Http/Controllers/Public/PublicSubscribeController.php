<?php

namespace App\Modules\AdminConsole\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Infrastructure\Persistence\Models\SubscriptionPlan;
use Inertia\Inertia;
use Inertia\Response;

final class PublicSubscribeController extends Controller
{
    public function show(string $locale, string $planId): Response
    {
        $plan = SubscriptionPlan::query()
            ->where('is_active', true)
            ->findOrFail($planId);

        return Inertia::render('public/Subscribe', [
            'plan' => [
                'id' => (string) $plan->id,
                'name' => $plan->name,
                'name_ar' => $plan->name_ar,
                'description' => $plan->description,
                'description_ar' => $plan->description_ar,
                'is_trial' => $plan->is_trial,
                'duration_days' => $plan->duration_days,
                'price' => $plan->price,
                'currency' => $plan->currency,
                'max_events' => $plan->max_events,
                'max_attendees' => $plan->max_attendees,
                'max_devices' => $plan->max_devices,
            ],
        ]);
    }
}
