<?php

namespace App\Modules\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlatformChatRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'locale' => ['sometimes', Rule::in(['en', 'ar'])],
            'history' => ['sometimes', 'array', 'max:12'],
            'history.*.role' => ['required_with:history', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ];
    }
}
