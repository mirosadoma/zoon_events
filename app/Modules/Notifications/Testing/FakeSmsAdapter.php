<?php

namespace App\Modules\Notifications\Testing;

use App\Modules\Notifications\Contracts\NotificationAdapter;
use App\Modules\Notifications\Domain\NotificationRequest;
use App\Modules\Notifications\Domain\NotificationResult;
use App\Modules\Notifications\Domain\NotificationStatus;

final class FakeSmsAdapter implements NotificationAdapter
{
    /** @var list<NotificationRequest> */
    public array $sent = [];

    public function __construct(private ?NotificationResult $next = null) {}

    public function send(NotificationRequest $request): NotificationResult
    {
        $this->sent[] = $request;

        return $this->next ?? new NotificationResult(NotificationStatus::Accepted, 'fake-sms-'.$request->notificationId);
    }
}
