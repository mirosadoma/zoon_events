<?php

namespace App\Modules\Integrations\Domain;

enum AdapterStatus: string
{
    case Succeeded = 'succeeded';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Unavailable = 'unavailable';
    case Unknown = 'unknown';
}
