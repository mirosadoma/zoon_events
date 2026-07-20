<?php

namespace App\Modules\Identity\Application\Actions;

use App\Models\User;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Identity\Mail\VisitorAccountReminderMail;
use App\Modules\Identity\Mail\VisitorCredentialsMail;
use App\Modules\Shared\Domain\LifecycleStatus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

final readonly class CreateOrLinkVisitorAccount
{
    /**
     * @return array{created: bool, user: User}
     */
    public function execute(
        string $attendeeId,
        string $email,
        string $name,
        string $locale = 'en',
    ): array {
        $email = mb_strtolower(trim($email));
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $attendee = Attendee::query()->findOrFail($attendeeId);

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if ($attendee->user_id === null) {
                $attendee->forceFill(['user_id' => $existing->id])->save();
            }

            $loginUrl = url('/'.$resolvedLocale.'/login');
            Mail::to($email)->send(new VisitorAccountReminderMail(
                email: $email,
                loginUrl: $loginUrl,
                preferredLocale: $resolvedLocale,
            ));

            return ['created' => false, 'user' => $existing];
        }

        $password = 'ZON@'.str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

        $user = User::query()->create([
            'name' => trim($name) !== '' ? trim($name) : 'Visitor',
            'email' => $email,
            'type' => 'visitor',
            'password' => Hash::make($password),
            'status' => LifecycleStatus::Active->value,
            'preferred_locale' => $resolvedLocale,
        ]);

        $attendee->forceFill(['user_id' => $user->id])->save();

        $loginUrl = url('/'.$resolvedLocale.'/login');
        Mail::to($email)->send(new VisitorCredentialsMail(
            email: $email,
            password: $password,
            loginUrl: $loginUrl,
            preferredLocale: $resolvedLocale,
        ));

        return ['created' => true, 'user' => $user];
    }
}
