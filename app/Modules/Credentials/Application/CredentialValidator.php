<?php

namespace App\Modules\Credentials\Application;

use App\Modules\Credentials\Application\Validation\CredentialValidator as Validator;

/** @deprecated Use Application\Validation\CredentialValidator. */
final readonly class CredentialValidator
{
    public function __construct(private Validator $validator) {}

    /** @return array{credential_id:string,status:string,event_id:string} */
    public function validate(string $token): array
    {
        return $this->validator->validate($token);
    }
}
