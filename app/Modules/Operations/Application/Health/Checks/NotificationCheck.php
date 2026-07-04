<?php

namespace App\Modules\Operations\Application\Health\Checks;

use App\Modules\Operations\Application\Configuration\ConfigurationValidator;

final class NotificationCheck extends AbstractConfigurationHealthCheck
{
    public function __construct(ConfigurationValidator $validator)
    {
        parent::__construct($validator, ['NOTIFICATIONS_', 'UNIFONIC_', 'MAIL_']);
    }

    public function category(): string
    {
        return 'notifications';
    }
}
