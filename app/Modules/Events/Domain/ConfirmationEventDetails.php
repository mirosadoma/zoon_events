<?php

namespace App\Modules\Events\Domain;

final readonly class ConfirmationEventDetails
{
    public function __construct(public string $nameEn, public string $nameAr) {}

    public function name(string $locale): string
    {
        return $locale === 'ar' ? $this->nameAr : $this->nameEn;
    }
}
