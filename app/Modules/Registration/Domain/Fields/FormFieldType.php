<?php

namespace App\Modules\Registration\Domain\Fields;

enum FormFieldType: string
{
    case Text = 'text';
    case Email = 'email';
    case Phone = 'phone';
    case Select = 'select';
    case Number = 'number';
    case Date = 'date';
    case MultiSelect = 'multi_select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Consent = 'consent';
    case Hidden = 'hidden';
}
