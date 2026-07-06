<?php

namespace App\Modules\Credentials\Infrastructure\Persistence;

use App\Modules\Credentials\Contracts\CredentialPersonalDataAnonymizer;
use App\Modules\Credentials\Domain\Events\CredentialLifecycleChanged;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;

final class DatabaseCredentialPersonalDataAnonymizer implements CredentialPersonalDataAnonymizer
{
    public function revokeForAttendee(string $tenantId, string $attendeeId): void
    {
        Credential::query()
            ->where('tenant_id', $tenantId)
            ->where('attendee_id', $attendeeId)
            ->whereNotIn('status', ['revoked', 'superseded'])
            ->get()
            ->each(function (Credential $credential) use ($tenantId): void {
                $credential->forceFill([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'revocation_reason' => 'attendee_data_anonymized',
                ])->save();

                event(new CredentialLifecycleChanged(
                    $tenantId,
                    $credential->event_id,
                    $credential->id,
                    'revoked',
                ));
            });
    }
}
