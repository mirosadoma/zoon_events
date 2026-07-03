<?php

namespace App\Modules\Identity\Contracts;

interface MfaAuthenticator
{
    public function authenticate(array $credentials): never;
}
