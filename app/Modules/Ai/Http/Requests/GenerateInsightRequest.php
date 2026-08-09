<?php

namespace App\Modules\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GenerateInsightRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'metric_window' => ['required', Rule::in(['all_time', 'last_7_days', 'last_14_days', 'last_30_days'])],
            'locale' => ['required', Rule::in(['en', 'ar'])],
            'refresh' => ['sometimes', 'boolean'],
        ];
    }
}
