<?php

namespace App\Modules\AdminConsole\Application\Actions;

use App\Models\User;
use App\Modules\AdminConsole\Application\SiteSettingsRepository;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\OrganizerRegistrationRequest;
use App\Modules\AdminConsole\Mail\OrganizerRejectedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class RejectOrganizerRegistrationAction
{
    public function execute(OrganizerRegistrationRequest $request, User $reviewer, string $reason): OrganizerRegistrationRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This request was already reviewed.']);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A rejection reason is required.']);
        }

        $request->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $settings = app(SiteSettingsRepository::class)->current();

        Mail::to($request->email)->send(new OrganizerRejectedMail(
            organizerName: $request->name,
            organizationName: $request->organization_name,
            reason: $reason,
            appName: $settings->app_name_en,
            supportEmail: $settings->support_email ?? config('mail.from.address'),
        ));

        return $request->fresh();
    }
}
