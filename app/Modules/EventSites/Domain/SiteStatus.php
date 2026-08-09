<?php

namespace App\Modules\EventSites\Domain;

enum SiteStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';

    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
