<?php

namespace App\Modules\Events\Application\Actions;

use App\Exceptions\FoundationException;
use App\Modules\Events\Application\Support\InviteCodeGenerator;
use App\Modules\Events\Application\Support\PublicRegistrationUrlBuilder;
use App\Modules\Events\Domain\EventTier;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use App\Modules\Events\Mail\PrivateEventInviteMail;
use App\Modules\Shared\Application\DataProtection\PersonalDataGuard;
use Illuminate\Support\Facades\Mail;

final readonly class SendPrivateEventInvites
{
    public function __construct(
        private InviteCodeGenerator $codes,
        private PublicRegistrationUrlBuilder $urls,
        private PersonalDataGuard $guard,
    ) {}

    /**
     * @param  list<array{email: string, name?: string, phone?: string}>  $invitees
     * @return array{sent:int,renewed:int,invites:list<array{id:string,email:string,name:?string,code:string}>}
     */
    public function execute(Event $event, array $invitees, string $locale = 'en'): array
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

        $normalized = collect($invitees)
            ->map(function ($invitee): array {
                $email = is_array($invitee) ? ($invitee['email'] ?? '') : (string) $invitee;
                $name = is_array($invitee) ? ($invitee['name'] ?? null) : null;
                $phone = is_array($invitee) ? ($invitee['phone'] ?? null) : null;

                return [
                    'email' => strtolower(trim($email)),
                    'name' => $name !== null && $name !== '' ? trim($name) : null,
                    'phone' => $phone !== null && trim((string) $phone) !== '' ? trim((string) $phone) : '',
                ];
            })
            ->filter(fn (array $invitee): bool => filter_var($invitee['email'], FILTER_VALIDATE_EMAIL) !== false)
            ->unique('email')
            ->values();

        $sent = 0;
        $renewed = 0;
        $invites = [];
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $eventName = $resolvedLocale === 'ar'
            ? ($event->name_ar ?: $event->name_en)
            : $event->name_en;
        $scope = "{$event->tenant_id}:{$event->id}:invite";

        foreach ($normalized as $invitee) {
            $email = $invitee['email'];
            $name = $invitee['name'];
            $phone = $invitee['phone'];
            $emailIndex = $this->guard->emailIndex($email);
            $encryptedEmail = $this->guard->encryptString($email, $scope);
            $encryptedName = $name !== null ? $this->guard->encryptString($name, $scope) : null;

            $existing = EventRegistrationInvite::query()
                ->where('event_id', $event->id)
                ->where(function ($builder) use ($email, $emailIndex): void {
                    $builder->where('email_index', $emailIndex)
                        ->orWhere('email', $email);
                })
                ->orderByDesc('id')
                ->first();

            if ($existing !== null) {
                // Keep a single invite row per email; invalidate older duplicates.
                EventRegistrationInvite::query()
                    ->where('event_id', $event->id)
                    ->where(function ($builder) use ($email, $emailIndex): void {
                        $builder->where('email_index', $emailIndex)
                            ->orWhere('email', $email);
                    })
                    ->whereKeyNot($existing->id)
                    ->update(['is_active' => false]);

                $existing->forceFill([
                    'email' => null,
                    'email_ciphertext' => $encryptedEmail['ciphertext'],
                    'email_index' => $emailIndex,
                    'name' => null,
                    'name_ciphertext' => $encryptedName['ciphertext'] ?? null,
                    'encryption_key_id' => $encryptedEmail['key_id'],
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
                    'email' => null,
                    'email_ciphertext' => $encryptedEmail['ciphertext'],
                    'email_index' => $emailIndex,
                    'name' => null,
                    'name_ciphertext' => $encryptedName['ciphertext'] ?? null,
                    'encryption_key_id' => $encryptedEmail['key_id'],
                    'code' => $this->codes->generateUnique($event->id),
                    'is_active' => true,
                    'invite_status' => 'not_registered',
                    'sent_at' => now(),
                ]);
                $sent++;
            }

            $url = $this->urls->forInvite($event, $invite, $resolvedLocale);

            try {
                $custom = app(\App\Modules\Events\Application\Support\ResolveEventEmailTemplate::class)->render(
                    $event,
                    \App\Modules\Events\Application\Support\ResolveEventEmailTemplate::TYPE_INVITATION,
                    $resolvedLocale,
                    [
                        'name' => $name ?? '',
                        'email' => $email,
                        'phone' => $phone,
                        'event' => $eventName,
                        'registration_url' => $url,
                        'qr' => '',
                    ],
                );

                if ($custom !== null) {
                    Mail::to($email)->send(new \App\Modules\Events\Mail\CustomEventEmailMail(
                        $custom['subject'],
                        $custom['html'],
                        $resolvedLocale,
                    ));
                } else {
                    Mail::to($email)->send(new PrivateEventInviteMail($eventName, $url, $resolvedLocale));
                }
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
                'email' => $email,
                'name' => $name,
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
