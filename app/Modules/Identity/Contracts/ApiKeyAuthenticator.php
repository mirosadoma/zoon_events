<?php

namespace App\Modules\Identity\Contracts;

interface ApiKeyAuthenticator
{
    public function authenticate(string $key): never;
}
