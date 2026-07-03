<?php

namespace App\Modules\Identity\Contracts;

interface ServiceTokenAuthenticator
{
    public function authenticate(string $token): never;
}
