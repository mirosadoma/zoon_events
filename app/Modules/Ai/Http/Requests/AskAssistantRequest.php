<?php

namespace App\Modules\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AskAssistantRequest extends FormRequest
{
    public function rules(): array
    {
        $maxChars = (int) config('ai.assistant.max_question_chars', 1000);

        return [
            'conversation_id' => ['nullable', 'string', 'uuid'],
            'message' => ['required', 'string', "max:{$maxChars}"],
            'locale' => ['required', Rule::in(['en', 'ar'])],
        ];
    }
}
