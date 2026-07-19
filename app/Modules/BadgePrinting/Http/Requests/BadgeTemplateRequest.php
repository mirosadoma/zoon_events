<?php

namespace App\Modules\BadgePrinting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BadgeTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('badge.template.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'layout' => ['required', 'array'],
            'paper_size' => ['required', 'string', 'max:40'],
            'printer_type' => ['required', 'string', 'max:40'],
            'orientation' => ['nullable', 'string', 'in:portrait,landscape'],
            'background_color' => ['nullable', 'string', 'max:7'],
            'canvas_width' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'canvas_height' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ];
    }
}
