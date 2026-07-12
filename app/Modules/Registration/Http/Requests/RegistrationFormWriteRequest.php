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
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.visibility' => ['sometimes', 'in:public,internal'],
            'fields.*.condition' => ['sometimes', 'array'],
            'fields.*.help_en' => ['sometimes', 'nullable', 'string', 'max:500'],
            'fields.*.help_ar' => ['sometimes', 'nullable', 'string', 'max:500'],
            'fields.*.options' => ['sometimes', 'array', 'max:100'],
            'fields.*.options.*.id' => ['sometimes', 'string', 'max:64'],
            'fields.*.options.*.value' => ['sometimes', 'string', 'max:64'],
            'fields.*.options.*.label_en' => ['sometimes', 'string', 'max:160'],
            'fields.*.options.*.label_ar' => ['sometimes', 'string', 'max:160'],
            'fields.*.default' => ['sometimes', 'nullable'],
            'fields.*.validation' => ['sometimes', 'array'],
            'privacy_notice_version' => ['required', 'string', 'max:80'],
            'terms_version' => ['required', 'string', 'max:80'],
        ];
    }
}
