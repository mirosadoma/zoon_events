<?php

namespace App\Modules\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAssistantFaqRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'question_en' => ['required', 'string', 'max:500'],
            'question_ar' => ['required', 'string', 'max:500'],
            'answer_en' => ['required', 'string', 'max:5000'],
            'answer_ar' => ['required', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
