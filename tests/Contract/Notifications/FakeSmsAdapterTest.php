<?php

namespace Tests\Contract\Notifications;

use App\Modules\Notifications\Contracts\NotificationAdapter;
use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Testing\FakeSmsAdapter;

final class FakeSmsAdapterTest extends NotificationAdapterContractTestCase
{
    protected function adapter(): NotificationAdapter
    {
        return new FakeSmsAdapter;
    }

    protected function channel(): NotificationChannel
    {
        return NotificationChannel::Sms;
    }
}
