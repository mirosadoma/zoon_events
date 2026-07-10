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
    case Dropdown = 'dropdown';
    case MultiSelect = 'multi_select';
    case Checkbox = 'checkbox';
    case Hidden = 'hidden';
    case Consent = 'consent';
}
