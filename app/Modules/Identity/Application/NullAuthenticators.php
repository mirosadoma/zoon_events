<?php

namespace App\Modules\Identity\Application;

use App\Exceptions\FoundationException;
use App\Modules\Identity\Contracts\ApiKeyAuthenticator;
use App\Modules\Identity\Contracts\MfaAuthenticator;
use App\Modules\Identity\Contracts\ServiceTokenAuthenticator;

final class NullAuthenticators implements ApiKeyAuthenticator, MfaAuthenticator, ServiceTokenAuthenticator
{
    public function authenticate(array|string $credentials): never
    {
        throw FoundationException::forbidden('authentication_extension_unconfigured', 'This authentication method is not configured.');
    }
}
