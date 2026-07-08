<?php

namespace Tests\Support;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait CreatesIdentityAttendeeFixture
{
    /** @return array{fixture:array<string,mixed>,order:Order,attendee:Attendee,accessToken:string} */
    protected function createIdentityAttendeeFixture(): array
    {
        $fixture = $this->createRegistrationFixture();
        $response = $this->withHeader('Idempotency-Key', 'identity-attendee-'.Str::lower((string) Str::ulid()))
            ->postJson(
                "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
                $this->registrationPayload($fixture),
            )->assertCreated();

        $order = Order::query()
            ->where('public_reference', $response->json('data.public_reference'))
            ->firstOrFail();
        $attendee = Attendee::query()
            ->where('tenant_id', $fixture['tenant']->id)
            ->where('event_id', $fixture['event']->id)
            ->firstOrFail();

        return [
            'fixture' => $fixture,
            'order' => $order,
            'attendee' => $attendee,
            'accessToken' => (string) $response->json('data.access_token'),
        ];
    }

    /** @param array{fixture:array<string,mixed>,attendee:Attendee,accessToken:string} $context */
    protected function identityAttendeeHeaders(array $context, string $idempotencyKey = 'identity-test'): array
    {
        return [
            'X-Order-Access-Token' => $context['accessToken'],
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    /** @param array<string, mixed> $payload */
    protected function signedGovernmentCallback(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac(
            'sha256',
            $body,
            (string) config('identity-verification.government_callback_secret'),
        );

        return $this->call(
            'POST',
            '/api/v1/identity/providers/government/callback',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Identity-Callback-Signature' => $signature,
            ],
            $body,
        );
    }
}
