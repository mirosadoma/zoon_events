<?php

namespace App\Modules\Events\Domain;

enum EventTier: string
{
    case Corporate = 'corporate';
    case Public = 'public';
    case Vip = 'vip';
    case Vvip = 'vvip';
}
