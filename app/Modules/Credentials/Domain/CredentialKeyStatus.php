<?php

namespace App\Modules\Credentials\Domain;

enum CredentialKeyStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case VerifyOnly = 'verify_only';
    case Retired = 'retired';
    case Compromised = 'compromised';
}
