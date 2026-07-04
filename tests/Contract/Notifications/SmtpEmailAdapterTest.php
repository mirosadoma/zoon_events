<?php

namespace Tests\Contract\Notifications;

use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Domain\NotificationRequest;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Infrastructure\Adapters\SmtpEmailAdapter;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('notifications')]
final class SmtpEmailAdapterTest extends TestCase
{
    public function test_it_uses_the_configured_mail_transport_without_network_access(): void
    {
        Mail::fake();
        $result = (new SmtpEmailAdapter)->send(new NotificationRequest(
            'tenant', 'notice', NotificationChannel::Email, 'person@example.test',
            'sender@example.test', 'Confirmed', '<p>Safe body</p>', 'en', 'corr', 'idem',
        ));

        self::assertSame(NotificationStatus::Sent, $result->status);
        self::assertStringStartsWith('smtp-', (string) $result->providerMessageId);
    }

    public function test_it_rejects_a_channel_mismatch_without_exposing_destination(): void
    {
        $result = (new SmtpEmailAdapter)->send(new NotificationRequest(
            'tenant', 'notice', NotificationChannel::Sms, '+966500000000',
            'sender', 'Confirmed', 'Body', 'en', 'corr', 'idem',
        ));

        self::assertSame(NotificationStatus::PermanentFailure, $result->status);
        self::assertSame('channel_mismatch', $result->reasonCode);
    }
}
