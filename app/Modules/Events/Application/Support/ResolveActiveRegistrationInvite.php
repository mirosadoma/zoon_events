<?php

namespace App\Modules\Events\Application\Support;

use App\Exceptions\FoundationException;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;

final readonly class ResolveActiveRegistrationInvite
{
    public function __construct(
        private PublicRegistrationUrlBuilder $urls,
    ) {}

    public function requireForPrivateEvent(Event $event, ?string $code): ?EventRegistrationInvite
    {
        $code = is_string($code) ? trim($code) : '';

        if ($this->urls->requiresInvite($event)) {
            if ($code === '' || ! preg_match('/^\d{10}$/', $code)) {
                throw FoundationException::forbidden(
                    'invite_required',
                    'A valid private invitation link is required to register for this event.',
                );
            }

            return $this->findActive($event, $code);
        }

        if ($code === '') {
            return null;
        }

        if (! preg_match('/^\d{10}$/', $code)) {
            throw FoundationException::forbidden(
                'invite_invalid',
                'This invitation link is invalid.',
            );
        }

        return $this->findActive($event, $code);
    }

    public function findActive(Event $event, string $code): EventRegistrationInvite
    {
        $invite = EventRegistrationInvite::query()
            ->where('event_id', $event->id)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if ($invite === null) {
            throw FoundationException::forbidden(
                'invite_inactive',
                'This invitation link is no longer active.',
            );
        }

        return $invite;
    }
}
