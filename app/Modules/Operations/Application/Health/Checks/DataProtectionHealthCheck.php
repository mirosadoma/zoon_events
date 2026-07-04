<?php

namespace App\Modules\Operations\Application\Health\Checks;

use App\Modules\Operations\Application\Configuration\ConfigurationValidator;

final class DataProtectionHealthCheck extends AbstractConfigurationHealthCheck
{
    public function __construct(ConfigurationValidator $validator)
    {
        parent::__construct($validator, ['PERSONAL_DATA_', 'BLIND_INDEX_']);
    }

    public function category(): string
    {
        return 'data_protection';
    }
}
