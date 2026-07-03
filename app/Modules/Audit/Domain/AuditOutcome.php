<?php

namespace App\Modules\Audit\Domain;

enum AuditOutcome: string
{
    case Succeeded = 'succeeded';
    case Denied = 'denied';
    case Failed = 'failed';
}
