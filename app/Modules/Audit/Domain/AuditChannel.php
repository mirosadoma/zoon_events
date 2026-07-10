<?php

namespace App\Modules\Audit\Domain;

enum AuditChannel: string
{
    case Api = 'api';
    case Console = 'console';
    case Job = 'job';
    case Scheduler = 'scheduler';
    case System = 'system';
}
