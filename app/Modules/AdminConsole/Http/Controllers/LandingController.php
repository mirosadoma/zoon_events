<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SiteSettingsRepository;
use App\Modules\Subscriptions\Infrastructure\Persistence\Models\SubscriptionPlan;
use Inertia\Inertia;
use Inertia\Response;

final class LandingController extends Controller
{
    public function __invoke(SiteSettingsRepository $settings): Response
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => [
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
            ])
            ->all();

        return Inertia::render('Landing', array_merge($settings->toPublicArray(), [
            'plans' => $plans,
        ]));
    }
}
