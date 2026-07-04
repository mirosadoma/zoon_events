<?php

namespace App\Modules\Payments\Domain;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case ActionRequired = 'action_required';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Unknown = 'unknown';
}
