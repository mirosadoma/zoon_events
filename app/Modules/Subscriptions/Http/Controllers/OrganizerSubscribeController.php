<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Subscriptions\Application\Actions\SubscribeOrganizerAction;
use App\Modules\Subscriptions\Infrastructure\Persistence\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrganizerSubscribeController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly SubscribeOrganizerAction $action,
    ) {}

    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:8', 'max:1024', 'confirmed'],
            'organization_name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'in:en,ar'],
            'timezone' => ['nullable', 'string', 'max:50'],
        ]);

        $email = mb_strtolower($validated['email']);

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A user with this email already exists.',
            ]);
        }

        $plan = SubscriptionPlan::query()
            ->where('id', $validated['plan_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if (! $plan->isFree() && empty($validated['payment_reference'])) {
            throw ValidationException::withMessages([
                'payment_reference' => 'Payment is required for this plan.',
            ]);
        }

        $validated['email'] = $email;

        $result = $this->action->execute($validated, $plan);

        return $this->success([
            'message' => 'Registration successful. Login credentials sent to your email.',
            'tenant_id' => $result['tenant']->id,
        ], 201);
    }
}
