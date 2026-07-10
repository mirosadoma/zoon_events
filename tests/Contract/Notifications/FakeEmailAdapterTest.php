<?php

namespace Tests\Contract\Notifications;

use App\Modules\Notifications\Contracts\NotificationAdapter;
use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Testing\FakeEmailAdapter;

final class FakeEmailAdapterTest extends NotificationAdapterContractTestCase
{
    protected function adapter(): NotificationAdapter
    {
        return new FakeEmailAdapter;
    }

    protected function channel(): NotificationChannel
    {
        return NotificationChannel::Email;
    }
}
