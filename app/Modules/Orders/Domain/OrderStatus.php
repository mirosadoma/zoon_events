<?php

namespace App\Modules\Orders\Domain;

enum OrderStatus: string
{
    case Draft = 'draft';
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => in_array($next, [self::PendingPayment, self::Paid, self::Failed, self::Cancelled], true),
            self::PendingPayment => in_array($next, [self::Paid, self::Failed, self::Cancelled], true),
            self::Paid => in_array($next, [self::PartiallyRefunded, self::Refunded], true),
            self::PartiallyRefunded => $next === self::Refunded,
            default => false,
        };
    }
}
