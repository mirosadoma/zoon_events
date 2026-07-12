<?php

namespace App\Modules\Notifications\Contracts;

interface ConfirmationIntentCreator
{
    public function create(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $orderId,
        ?string $credentialId,
        string $email,
        string $locale,
        ?string $phone = null,
    ): string;
}
