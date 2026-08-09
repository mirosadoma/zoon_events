<?php

namespace App\Modules\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAssistantFaqRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'question_en' => ['sometimes', 'string', 'max:500'],
            'question_ar' => ['sometimes', 'string', 'max:500'],
            'answer_en' => ['sometimes', 'string', 'max:5000'],
            'answer_ar' => ['sometimes', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
