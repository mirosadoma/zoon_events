<?php

namespace App\Modules\Notifications\Contracts;

use App\Modules\Notifications\Domain\NotificationRequest;
use App\Modules\Notifications\Domain\NotificationResult;

interface NotificationAdapter
{
    public function send(NotificationRequest $request): NotificationResult;
}
