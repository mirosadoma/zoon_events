<?php

namespace App\Modules\Operations\Application\Health\Checks;

use App\Modules\Operations\Application\Configuration\ConfigurationValidator;

final class NotificationConfigurationHealthCheck extends AbstractConfigurationHealthCheck
{
    public function __construct(ConfigurationValidator $validator)
    {
        parent::__construct($validator, ['NOTIFICATIONS_', 'UNIFONIC_']);
    }

    public function category(): string
    {
        return 'notifications';
    }
}
