<?php

namespace App\Modules\Operations\Application\Health\Checks;

use App\Modules\Operations\Application\Configuration\ConfigurationValidator;

final class PaymentConfigurationHealthCheck extends AbstractConfigurationHealthCheck
{
    public function __construct(ConfigurationValidator $validator)
    {
        parent::__construct($validator, ['PAYMENTS_', 'MOYASAR_']);
    }

    public function category(): string
    {
        return 'payments';
    }
}
