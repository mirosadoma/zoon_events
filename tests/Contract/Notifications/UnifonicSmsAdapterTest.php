<?php

namespace Tests\Contract\Notifications;

use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Domain\NotificationRequest;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Infrastructure\Adapters\UnifonicSmsAdapter;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('notifications')]
final class UnifonicSmsAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('TEST_UNIFONIC_SECRET=secret-value');
        config()->set('notifications.unifonic.app_sid_reference', 'TEST_UNIFONIC_SECRET');
        config()->set('notifications.allow_network', true);
    }

    public function test_it_sends_a_redaction_safe_request_with_idempotency(): void
    {
        Http::fake(['*' => Http::response(['data' => ['MessageID' => 'msg-1']], 200)]);
        $result = (new UnifonicSmsAdapter)->send($this->request());

        self::assertSame(NotificationStatus::Accepted, $result->status);
        self::assertSame('msg-1', $result->providerMessageId);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Idempotency-Key', 'idem')
            && $request['AppSid'] === 'secret-value'
            && $request['Recipient'] === '+966500000000');
    }

    public function test_it_classifies_rate_limits_as_temporary(): void
    {
        Http::fake(['*' => Http::response([], 429)]);
        $result = (new UnifonicSmsAdapter)->send($this->request());

        self::assertSame(NotificationStatus::TemporaryFailure, $result->status);
        self::assertSame('rate_limited', $result->reasonCode);
    }

    private function request(): NotificationRequest
    {
        return new NotificationRequest(
            'tenant', 'notice', NotificationChannel::Sms, '+966500000000',
            'Zonetec', 'Confirmed', 'Safe body', 'ar', 'corr', 'idem',
        );
    }
}
