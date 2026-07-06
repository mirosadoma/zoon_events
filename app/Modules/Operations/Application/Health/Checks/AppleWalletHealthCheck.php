<?php

namespace App\Modules\Operations\Application\Health\Checks;

use App\Modules\Operations\Application\Configuration\ConfigurationValidator;

final class AppleWalletHealthCheck extends AbstractConfigurationHealthCheck
{
    public function __construct(ConfigurationValidator $validator)
    {
        parent::__construct($validator, ['WALLET_APPLE_']);
    }

    public function category(): string
    {
        return 'apple_wallet';
    }
}
