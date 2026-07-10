<?php

namespace App\Modules\Audit\Domain;

enum AuditScope: string
{
    case Tenant = 'tenant';
    case Platform = 'platform';
}
