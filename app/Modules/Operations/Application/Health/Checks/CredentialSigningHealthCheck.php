<?php

namespace App\Modules\Operations\Application\Health\Checks;

use App\Modules\Operations\Application\Configuration\ConfigurationValidator;

final class CredentialSigningHealthCheck extends AbstractConfigurationHealthCheck
{
    public function __construct(ConfigurationValidator $validator)
    {
        parent::__construct($validator, ['CREDENTIAL_KEY_']);
    }

    public function category(): string
    {
        return 'credential_signing';
    }
}
