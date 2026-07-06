<?php

namespace App\Modules\WalletPasses\Infrastructure\Persistence;

use App\Modules\WalletPasses\Contracts\WalletPassPersonalDataAnonymizer;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPassAppleDeviceRegistration;

final class DatabaseWalletPassPersonalDataAnonymizer implements WalletPassPersonalDataAnonymizer
{
    public function anonymizeForAttendee(string $tenantId, string $attendeeId): void
    {
        $passIds = WalletPass::query()
            ->where('tenant_id', $tenantId)
            ->where('attendee_id', $attendeeId)
            ->pluck('id');

        if ($passIds->isEmpty()) {
            return;
        }

        WalletPassAppleDeviceRegistration::query()
            ->whereIn('wallet_pass_id', $passIds)
            ->delete();

        // attendee_id is retained: it is a required, foreign-key-constrained column and the
        // attendee row it references has already had its personal data redacted by the caller.
        WalletPass::query()
            ->whereIn('id', $passIds)
            ->update([
                'status' => WalletPassStatus::Revoked,
                'pass_url' => null,
                'apple_authentication_token' => null,
                'updated_at' => now(),
            ]);
    }
}
