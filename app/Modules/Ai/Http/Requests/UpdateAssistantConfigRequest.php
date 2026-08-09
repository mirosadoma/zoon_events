<?php

namespace App\Modules\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAssistantConfigRequest extends FormRequest
{
    public function rules(): array
    {
        $maxDailyLimit = (int) config('ai.assistant.event_questions_per_day', 500);

        return [
            'enabled' => ['sometimes', 'boolean'],
            'display_name.en' => ['nullable', 'string', 'max:120'],
            'display_name.ar' => ['nullable', 'string', 'max:120'],
            'greeting.en' => ['nullable', 'string', 'max:500'],
            'greeting.ar' => ['nullable', 'string', 'max:500'],
            'fallback_action' => ['sometimes', Rule::in(['registration', 'contact', 'none'])],
            'fallback_contact_email' => [
                'nullable',
                'email',
                'max:190',
                Rule::requiredIf(fn () => $this->input('fallback_action') === 'contact'),
            ],
            'daily_question_limit' => ['sometimes', 'integer', 'min:1', "max:{$maxDailyLimit}"],
        ];
    }
}
