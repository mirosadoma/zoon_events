<?php

namespace App\Modules\Registration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegistrationFormWriteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'fields' => ['required', 'array', 'min:1', 'max:100'],
            'fields.*.key' => ['required', 'string', 'max:64'],
            'fields.*.type' => ['required', 'string'],
            'fields.*.label_en' => ['required', 'string', 'max:160'],
            'fields.*.label_ar' => ['required', 'string', 'max:160'],
            'fields.*.placeholder_en' => ['sometimes', 'nullable', 'string', 'max:200'],
            'fields.*.placeholder_ar' => ['sometimes', 'nullable', 'string', 'max:200'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.visibility' => ['sometimes', 'in:public,internal'],
            'fields.*.width' => ['sometimes', 'in:full,half,third'],
            'fields.*.condition' => ['sometimes', 'array'],
            'fields.*.help_en' => ['sometimes', 'nullable', 'string', 'max:500'],
            'fields.*.help_ar' => ['sometimes', 'nullable', 'string', 'max:500'],
            'fields.*.content' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'fields.*.options' => ['sometimes', 'array', 'max:100'],
            'fields.*.options.*.id' => ['sometimes', 'string', 'max:64'],
            'fields.*.options.*.value' => ['sometimes', 'string', 'max:64'],
            'fields.*.options.*.label_en' => ['sometimes', 'string', 'max:160'],
            'fields.*.options.*.label_ar' => ['sometimes', 'string', 'max:160'],
            'fields.*.default' => ['sometimes', 'nullable'],
            'fields.*.validation' => ['sometimes', 'array'],
            'privacy_notice_version' => ['required', 'string', 'max:80'],
            'terms_version' => ['required', 'string', 'max:80'],
            'theme' => ['sometimes', 'nullable', 'array'],
            'theme.primary_color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'theme.accent_color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'theme.background_color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'theme.font_family' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
