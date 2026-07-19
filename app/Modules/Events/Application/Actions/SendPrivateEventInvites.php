<?php

namespace App\Modules\Events\Application\Actions;

use App\Exceptions\FoundationException;
use App\Modules\Events\Application\Support\InviteCodeGenerator;
use App\Modules\Events\Application\Support\PublicRegistrationUrlBuilder;
use App\Modules\Events\Domain\EventTier;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use App\Modules\Events\Mail\PrivateEventInviteMail;
use Illuminate\Support\Facades\Mail;

final readonly class SendPrivateEventInvites
{
    public function __construct(
        private InviteCodeGenerator $codes,
        private PublicRegistrationUrlBuilder $urls,
    ) {}

    /**
     * @param  list<string>  $emails
     * @return array{sent:int,renewed:int,invites:list<array{id:string,email:string,code:string}>}
     */
    public function execute(Event $event, array $emails, string $locale = 'en'): array
    {
        if (! in_array($event->tier, [EventTier::Private->value, EventTier::Both->value], true)) {
            throw FoundationException::validation(
                'invite_not_allowed',
                'Invites are only available for private events.',
            );
        }

        if (! $this->urls->isShareable($event)) {
            throw FoundationException::validation(
                'event_not_shareable',
                'Publish the event and activate a registration form before sending invites.',
            );
        }

        $normalized = collect($emails)
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values();

        $sent = 0;
        $renewed = 0;
        $invites = [];
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $eventName = $resolvedLocale === 'ar'
            ? ($event->name_ar ?: $event->name_en)
            : $event->name_en;

        foreach ($normalized as $email) {
            $existing = EventRegistrationInvite::query()
                ->where('event_id', $event->id)
                ->where('email', $email)
                ->orderByDesc('id')
                ->first();

            if ($existing !== null) {
                // Keep a single invite row per email; invalidate older duplicates.
                EventRegistrationInvite::query()
                    ->where('event_id', $event->id)
                    ->where('email', $email)
                    ->whereKeyNot($existing->id)
                    ->update(['is_active' => false]);

                $existing->forceFill([
                    'code' => $this->codes->generateUnique($event->id),
                    'is_active' => true,
                    'used_at' => null,
                    'sent_at' => now(),
                    'invite_status' => $existing->invite_status === 'attended' || $existing->invite_status === 'not_attended'
                        ? $existing->invite_status
                        : 'not_registered',
                ])->save();

                $invite = $existing->fresh() ?? $existing;
                $renewed++;
            } else {
                $invite = EventRegistrationInvite::query()->create([
                    'tenant_id' => $event->tenant_id,
                    'event_id' => $event->id,
                    'email' => $email,
                    'code' => $this->codes->generateUnique($event->id),
                    'is_active' => true,
                    'invite_status' => 'not_registered',
                    'sent_at' => now(),
                ]);
                $sent++;
            }

            $url = $this->urls->forInvite($event, $invite, $resolvedLocale);

            try {
                Mail::to($email)->send(new PrivateEventInviteMail($eventName, $url, $resolvedLocale));
            } catch (\Throwable $exception) {
                report($exception);

                throw FoundationException::validation(
                    'invite_mail_failed',
                    'Invitation was saved but the email could not be sent. Check mail settings and try again.',
                );
            }

            $invite->forceFill(['sent_at' => now()])->save();

            $invites[] = [
                'id' => (string) $invite->id,
                'email' => $invite->email,
                'code' => $invite->code,
            ];
        }

        return [
            'sent' => $sent,
            'renewed' => $renewed,
            'invites' => $invites,
        ];
    }
}
