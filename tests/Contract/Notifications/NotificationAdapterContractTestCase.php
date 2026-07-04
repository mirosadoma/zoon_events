<?php

namespace Tests\Contract\Notifications;

use App\Modules\Notifications\Contracts\NotificationAdapter;
use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Domain\NotificationRequest;
use App\Modules\Notifications\Domain\NotificationStatus;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('notifications')]
abstract class NotificationAdapterContractTestCase extends TestCase
{
    abstract protected function adapter(): NotificationAdapter;

    abstract protected function channel(): NotificationChannel;

    public function test_adapter_returns_a_documented_status_without_mutating_request(): void
    {
        $request = new NotificationRequest(
            '01TENANT', '01NOTICE', $this->channel(), 'recipient@example.test',
            'sender@example.test', 'Subject', 'Body', 'en', 'corr-1', 'idem-1',
        );
        $result = $this->adapter()->send($request);

        self::assertContains($result->status, NotificationStatus::cases());
        self::assertSame('01NOTICE', $request->notificationId);
    }
}
