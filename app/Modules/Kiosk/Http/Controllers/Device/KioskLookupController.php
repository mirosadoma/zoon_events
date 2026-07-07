<?php

namespace App\Modules\Kiosk\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Application\Validation\CredentialValidator;
use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use App\Modules\Notifications\Application\NotificationAdapterRegistry;
use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Domain\NotificationRequest;
use App\Modules\Scanning\Application\Queries\LookupAttendeesQuery;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSetting;
use App\Modules\Scanning\Http\Requests\AttendeeLookupRequest;
use App\Modules\Scanning\Http\Resources\AttendeeLookupResource;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use App\Modules\Shared\Http\Problems\Phase3Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class KioskLookupController extends \App\Http\Controllers\Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly KioskSessionContextStore $kioskContexts,
        private readonly CredentialValidator $credentials,
        private readonly LookupAttendeesQuery $lookup,
        private readonly NotificationAdapterRegistry $notifications,
        private readonly PersonalDataCipher $cipher,
    ) {}

    public function store(AttendeeLookupRequest $request): \Illuminate\Http\JsonResponse
    {
        $context  = $this->kioskContexts->current();
        $tenantId = $context->tenantId;
        $eventId  = $context->eventId;

        if ($request->filled('qr_payload')) {
            $validated = $this->credentials->validate(
                $request->string('qr_payload')->toString(),
                $tenantId,
                $eventId,
            );
            $result = [
                'too_many' => false,
                'matches'  => [[
                    'attendee_id'       => null,
                    'credential_id'     => $validated['credential_id'],
                    'display_name'      => null,
                    'ticket_type_label' => null,
                    'checkin_status'    => 'not_checked_in',
                ]],
            ];
        } else {
            $fragment   = $request->string('query')->toString();
            $maxMatches = (int) config('printing.lookup.max_matches', 8);
            $result = $this->lookup->search($tenantId, $eventId, $fragment, $maxMatches);

            if ($result['too_many']) {
                throw Phase3Problem::make('lookup_too_many_matches');
            }

            $settings = EventCheckInSetting::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->first();

            if ($settings?->lookup_confirmation_required && $result['matches'] !== []) {
                $firstMatch = $result['matches'][0];
                $attendeeId = $firstMatch['attendee_id'] ?? null;

                if (! is_string($attendeeId) || $attendeeId === '') {
                    throw Phase3Problem::make('lookup_confirmation_required');
                }

                $otpKey = "kiosk-lookup-otp:{$tenantId}:{$attendeeId}";
                $submittedCode = trim($request->string('confirmation_code')->toString());

                if ($submittedCode === '') {
                    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    Cache::put(
                        $otpKey,
                        hash('sha256', $code),
                        now()->addSeconds((int) config('printing.lookup.confirmation_code_ttl_seconds', 300))
                    );
                    $this->deliverLookupCode($tenantId, $eventId, $attendeeId, $code);
                    throw Phase3Problem::make('lookup_confirmation_required');
                }

                $expected = Cache::get($otpKey);
                if (! is_string($expected) || ! hash_equals($expected, hash('sha256', $submittedCode))) {
                    throw Phase3Problem::make('lookup_confirmation_invalid');
                }

                Cache::forget($otpKey);

                foreach ($result['matches'] as $match) {
                    $credentialId = $match['credential_id'] ?? null;
                    if (is_string($credentialId) && $credentialId !== '') {
                        Cache::put(
                            "kiosk-lookup-confirmed:{$tenantId}:{$eventId}:{$context->kioskId}:{$credentialId}",
                            true,
                            now()->addSeconds((int) config('printing.lookup.confirmation_code_ttl_seconds', 300))
                        );
                    }
                }
            }
        }

        return $this->success((new AttendeeLookupResource($result))->resolve());
    }

    private function deliverLookupCode(string $tenantId, string $eventId, string $attendeeId, string $code): void
    {
        $attendee = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($attendeeId);

        if ($attendee === null) {
            return;
        }

        $scope = "{$tenantId}:{$eventId}:attendee";
        $destination = null;
        try {
            if ($attendee->email_ciphertext !== null && $attendee->encryption_key_id !== null) {
                $destination = $this->cipher->decrypt([
                    'ciphertext' => $attendee->email_ciphertext,
                    'key_id' => $attendee->encryption_key_id,
                ], $scope);
            }
        } catch (\Throwable) {
            $destination = null;
        }

        $destination ??= 'kiosk.lookup@example.invalid';
        $adapterKey = (string) config('notifications.email_adapter', 'log');

        $this->notifications->get($adapterKey)->send(new NotificationRequest(
            tenantId: $tenantId,
            notificationId: (string) Str::ulid(),
            channel: NotificationChannel::Email,
            destination: $destination,
            senderReference: (string) config('mail.from.address', 'noreply@example.invalid'),
            subject: 'Lookup confirmation code',
            body: "Your confirmation code is {$code}",
            locale: app()->getLocale(),
            correlationId: (string) Str::uuid(),
            idempotencyKey: (string) Str::uuid(),
        ));
    }
}
